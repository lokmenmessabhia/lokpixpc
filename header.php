<?php
// Ensure session is started (ideally at the very top of your main script like search.php)
// If not already started, uncomment the line below IN YOUR MAIN SCRIPT, not necessarily here.
// session_start();

// Use require for essential files - will error if not found
require_once __DIR__ . '/db_connect.php';

// Fetch site settings
$settings = [];
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

// --- User & Admin Status ---
$user_id = $_SESSION['userid'] ?? null;
$isAdmin = false;
$emailVerified = false;
$user = null;
$profilePicture = 'https://i.top4top.io/p_3273sk4691.jpg'; // Default avatar
$unreadCount = 0;

// Check if $pdo connection exists before using it
if (isset($pdo) && $pdo instanceof PDO) {
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['email'])) {
        $currentUserEmail = $_SESSION['email'];
        try {
            // Fetch user details
            $stmtUser = $pdo->prepare("SELECT id, email_verified, profile_picture FROM users WHERE email = ? LIMIT 1");
            $stmtUser->execute([$currentUserEmail]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $user_id = $user['id'] ?? $user_id;
                $emailVerified = (bool)($user['email_verified'] ?? false);
                
                
            }
            // Check Admin Status
            $stmtAdmin = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
            $stmtAdmin->execute([$currentUserEmail]);
            $isAdmin = $stmtAdmin->fetchColumn() > 0;
            // Get Unread Messages
            $stmtMsg = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_email = ? AND read_status = 0");
            $stmtMsg->execute([$currentUserEmail]);
            $unreadCount = $stmtMsg->fetchColumn();
        } catch (PDOException $e) {
            error_log("Header DB Error: " . $e->getMessage());
        }
    } else {
        $user_id = null;
    }

    // --- Initialize Grouped Subcategories Array ---
    $groupedSubcategories = [
        'grid'         => [], // Will contain hardware components (subcategories 1-8)
        'peripherals'  => [], // Contains specific subcategory IDs
        'expansion'    => [], // Contains items whose parent is 9 or 10
        'accessories'  => [],
        'software'     => [],
        'prebuilt_pcs' => [],
        'laptops'      => [],
    ];
    $subcategoriesById = [];

    // --- Define Category ID mappings ---
    // Hardware for Grid - These are now subcategory IDs
    define('HARDWARE_CAT_IDS_ORDERED', [1, 2, 3, 4, 5, 6, 7, 8]); // Update these numbers to match your subcategory IDs
    define('HARDWARE_CAT_IDS', [1, 2, 3, 4, 5, 6, 7, 8]);

    // ***** NEW: Define Specific Subcategory IDs for the Peripherals List *****
    define('PERIPHERAL_SUBCATEGORY_IDS', [9,11, 12, 13, 14, 15 ,16, 17, 18, 19, 20, 27, 28, 29, 30, 31, 32, 33, 34, 35]);
    // ***** END NEW *****

    // IDs for Expansion & Networking Column (Parent IDs)
    define('EXPANSION_CAT_IDS', [10]); // Parent IDs for Sound Cards, Expansion Cards
    // Removed NETWORKING_CAT_IDS constant as IDs 16-20 are now explicitly in PERIPHERAL_SUBCATEGORY_IDS

    // Parent Category IDs for Completed Builds Dropdown
    define('PREBUILT_PC_PARENT_CAT_ID', 4);
    define('LAPTOP_PARENT_CAT_ID', 5);

    // Define Parent IDs for other lists if needed
    define('SOFTWARE_PARENT_CAT_ID', 21);
     define('ACCESSORIES_PARENT_CAT_ID', 22 );


    // --- Function to get Grid Category Info ---
    function getCategoryInfoForGrid($catId) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT s.id, s.name, s.image_path 
                                  FROM subcategories s 
                                  WHERE s.id = ? 
                                  LIMIT 1");
            $stmt->execute([$catId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return [
                    'name' => $result['name'],
                    'image' => $result['image_path'] ? $result['image_path'] : 'https://placehold.co/100x100/e2e8f0/64748b?text=N/A',
                    'link' => 'subcategory.php?id=' . $result['id']
                ];
            }
            return null;
        } catch (PDOException $e) {
            error_log("Error fetching subcategory info: " . $e->getMessage());
        return null;
        }
    }
    // --- End of function ---


    // --- Fetch and Group Categories/Subcategories ---
    try {
        // --- Step 1: Populate Grid Items (Hardware Components) ---
        $hardwareSubcatIds = [1, 2, 3, 4, 5, 6, 7, 8]; // Update with your actual subcategory IDs
        foreach ($hardwareSubcatIds as $subcatId) {
            $parentInfo = getCategoryInfoForGrid($subcatId);
            if ($parentInfo) {
                $groupedSubcategories['grid'][$subcatId] = $parentInfo;
            }
        }

        // --- Step 2: Fetch ALL Subcategories with Images ---
        $stmtAllSubcats = $pdo->query("SELECT id, name, category_id, image_path FROM subcategories ORDER BY name ASC");
        $allSubcategories = $stmtAllSubcats->fetchAll(PDO::FETCH_ASSOC);

        // --- Step 3: Group Subcategories into LISTS ---
        foreach ($allSubcategories as $subcat) {
            $subcategoriesById[$subcat['id']] = $subcat;
            $subcatId = (int)$subcat['id'];
            $parentId = (int)$subcat['category_id'];

            // Add image path to subcategory data
            $subcat['image'] = $subcat['image_path'] ?? 'https://placehold.co/100x100/e2e8f0/64748b?text=N/A';

            if (in_array($subcatId, PERIPHERAL_SUBCATEGORY_IDS)) {
                $groupedSubcategories['peripherals'][] = $subcat;
            }
            elseif (in_array($parentId, EXPANSION_CAT_IDS)) {
                $groupedSubcategories['expansion'][] = $subcat;
            }
            elseif ($parentId === PREBUILT_PC_PARENT_CAT_ID) {
                $groupedSubcategories['prebuilt_pcs'][] = $subcat;
            }
            elseif ($parentId === LAPTOP_PARENT_CAT_ID) {
                $groupedSubcategories['laptops'][] = $subcat;
            }
            elseif ($parentId === SOFTWARE_PARENT_CAT_ID) {
                $groupedSubcategories['software'][] = $subcat;
            }
            elseif ($parentId === ACCESSORIES_PARENT_CAT_ID) {
                $groupedSubcategories['accessories'][] = $subcat;
            }
        }

    } catch (PDOException $e) {
        error_log("Subcategory/Grid Grouping Fetch Error: " . $e->getMessage());
        $groupedSubcategories = [
            'grid'=>[], 'peripherals'=>[], 'expansion'=>[], 'accessories'=>[], 'software'=>[],
            'prebuilt_pcs' => [], 'laptops' => [],
        ];
    }

} else {
    // --- Database Connection Failed ---
    error_log("Header Error: \$pdo database connection is not available.");
    $groupedSubcategories = [ // Set defaults
        'grid'=>[], 'peripherals'=>[], 'expansion'=>[], 'accessories'=>[], 'software'=>[],
        'prebuilt_pcs' => [], 'laptops' => [],
    ];
    $user_id = null;
    $isAdmin = false;
    $emailVerified = false;
    $unreadCount = 0;
}

// --- Cart Count ---
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// --- Ensure all keys exist ---
$groupedSubcategories['grid']         = $groupedSubcategories['grid'] ?? [];
$groupedSubcategories['peripherals']  = $groupedSubcategories['peripherals'] ?? [];
$groupedSubcategories['expansion']    = $groupedSubcategories['expansion'] ?? [];
$groupedSubcategories['accessories']  = $groupedSubcategories['accessories'] ?? [];
$groupedSubcategories['software']     = $groupedSubcategories['software'] ?? [];
$groupedSubcategories['prebuilt_pcs'] = $groupedSubcategories['prebuilt_pcs'] ?? [];
$groupedSubcategories['laptops']      = $groupedSubcategories['laptops'] ?? [];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['site_name'] ?? 'EcoTech') ?></title>
    <meta name="description" content="<?= htmlspecialchars($settings['site_description'] ?? '') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($settings['meta_keywords'] ?? '') ?>">
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($settings['favicon'] ?? 'logo (1).png') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Special+Gothic+Expanded+One&display=swap" rel="stylesheet">

    <?php if (!empty($settings['header_scripts'])): ?>
        <?= $settings['header_scripts'] ?>
    <?php endif; ?>
    <style>
    /* --- CSS Reset & Base --- */
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: '<?= htmlspecialchars($settings['font_family'] ?? 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif') ?>';
        line-height: 1.5;
        background-color: #FFFFFF;
        color: #212529;
    }

    a {
        text-decoration: none;
        color: inherit;
    }

    button {
        cursor: pointer;
        font-family: inherit;
        border: none;
        background: none;
    }

    ul,
    li {
        list-style: none;
    }

    /* --- Color Variables (White Mode) --- */
    :root {
        --header-bg: #FFFFFF;
        --header-text: #343a40;
        --header-text-secondary: #6c757d;
        --header-accent: <?= htmlspecialchars($settings['primary_color'] ?? '#0d6efd') ?>;
        --header-accent-hover: <?= htmlspecialchars($settings['accent_color'] ?? '#0b5ed7') ?>;
        --header-accent-rgb: <?= implode(', ', sscanf($settings['primary_color'] ?? '#0d6efd', '#%02x%02x%02x')) ?>;
        --header-border: #dee2e6;
        --dropdown-bg: #FFFFFF;
        --dropdown-border: #dee2e6;
        --dropdown-hover-bg: #f8f9fa;
        --dropdown-shadow: rgba(0, 0, 0, 0.1);
        --mega-menu-grid-bg: #FFFFFF;
        --mega-menu-grid-bg-hover: #f8f9fa;
        --mega-menu-heading-color: #495057;

        --button-radius: 4px;
        --focus-ring-color: rgba(var(--header-accent-rgb), 0.25);

        --search-bg: #FFFFFF;
        --search-border: #ced4da;
        --search-focus-border: #86b7fe;
        --search-placeholder-text: #6c757d;

        --notification-bg: #dc3545;
        --notification-text: #FFFFFF;

        --warning-bg: #FFF3CD;
        --warning-text: #664d03;
        --warning-border: #FFEEBA;
        --warning-link: #523e02;
    }

    /* Dark mode if enabled */
    <?php if (($settings['theme_mode'] ?? 'light') === 'dark'): ?>
    :root {
        --header-bg: #1a1a1a;
        --header-text: #f8f9fa;
        --header-text-secondary: #adb5bd;
        --header-border: #343a40;
        --dropdown-bg: #1a1a1a;
        --dropdown-border: #343a40;
        --dropdown-hover-bg: #2d2d2d;
        --dropdown-shadow: rgba(0, 0, 0, 0.3);
        --mega-menu-grid-bg: #1a1a1a;
        --mega-menu-grid-bg-hover: #2d2d2d;
        --mega-menu-heading-color: #e9ecef;
        --search-bg: #2d2d2d;
        --search-border: #495057;
        --search-focus-border: #86b7fe;
        --search-placeholder-text: #adb5bd;
    }
    <?php endif; ?>

    /* --- Header & Banner --- */
    header {
        background-color: var(--header-bg);
        color: var(--header-text);
        box-shadow: 0 1px 3px var(--dropdown-shadow);
        border-bottom: 1px solid var(--header-border);
        z-index: 1030;
        position: relative; /* Needed for absolute positioning of mobile nav */
    }

    .email-verification-banner {
        background-color: var(--warning-bg) !important;
        color: var(--warning-text) !important;
        text-align: center;
        padding: 8px 15px !important;
        font-size: 0.85em;
        border-bottom: 1px solid var(--warning-border);
    }

    .email-verification-banner a {
        color: var(--warning-link) !important;
        text-decoration: underline !important;
        font-weight: 600;
    }

    .email-verification-banner a:hover {
        color: #000 !important;
    }

    /* --- Main Nav Bar --- */
    .main-header-nav {
        display: flex;
        align-items: center;
        padding: 0 20px;
        max-width: 1400px;
        margin: 0 auto;
        min-height: 60px;
        gap: 15px;
        flex-wrap: wrap;
    }

    .logo {
        flex-shrink: 0;
        margin-right: 15px;
    }

    .logo img {
        height: 30px;
        vertical-align: middle;
        display: block;
    }

    /* --- Center Nav --- */
    .category-nav-container {
        display: flex;
        flex-grow: 1;
        align-items: center;
        gap: 5px;
        margin: 0 auto; /* Centers the nav block if space is available */
        justify-content: center; /* Center items within the container */
    }

    .nav-item {
        position: relative;
    }

    /* Desktop styles for links/buttons in nav */
    .nav-link,
    .category-btn {
        display: inline-flex; /* Size to content, allow internal flex */
        align-items: center;
        gap: 6px;
        padding: 10px 12px;
        color: var(--header-text);
        font-size: 0.9rem;
        font-weight: 500;
        border-radius: var(--button-radius);
        transition: background-color 0.2s ease, color 0.2s ease;
        white-space: nowrap;
        position: relative;
        /* width: 100%; REMOVED - caused spacing issues */
        /* text-align: left; REMOVED - default LTR is sufficient */
    }

    .nav-link .fas,
    .nav-link .fa,
    .category-btn .fas,
    .category-btn .fa {
        font-size: 1em;
        color: var(--header-text-secondary);
        transition: color 0.2s ease;
        width: 1.1em;
        text-align: center;
        flex-shrink: 0;
    }

    .nav-link span,
    .category-btn span {
        /* flex-grow: 1; /* Only needed if chevron needs pushing */
    }

    .nav-link:hover,
    .category-btn:hover,
    .nav-item:hover > .nav-link, /* Covers cases where nav-item is hovered */
    .nav-item:hover > .category-btn {
        background-color: var(--dropdown-hover-bg);
        color: var(--header-accent);
    }

    .nav-link:hover .fas,
    .nav-link:hover .fa,
    .category-btn:hover .fas,
    .category-btn:hover .fa {
        color: var(--header-accent);
    }

    .nav-link:focus-visible,
    .category-btn:focus-visible {
        outline: none;
        background-color: var(--dropdown-hover-bg);
        color: var(--header-accent);
        box-shadow: 0 0 0 0.2rem var(--focus-ring-color);
    }

    /* Arrow for dropdowns */
    .nav-link.has-dropdown::after,
    .category-btn.has-dropdown::after {
        content: '\f078';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        font-size: 0.6em;
        margin-left: auto; /* Push chevron to the right */
        padding-left: 10px; /* Space before chevron */
        color: var(--header-text-secondary);
        transition: transform 0.2s ease;
    }

    /* Desktop hover rotation for dropdown arrow */
    .nav-item:hover > .nav-link.has-dropdown::after,
    .nav-item:hover > .category-btn.has-dropdown::after {
        transform: rotate(180deg);
        color: var(--header-accent);
    }


    /* --- Right Actions (User + Search) --- */
    .header-right-actions {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-left: auto; /* Pushes this section to the right */
        flex-shrink: 0;
    }

    .header-user-area {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .header-user-area a {
        color: var(--header-text-secondary);
        font-size: 1.1em;
        padding: 6px;
        border-radius: var(--button-radius);
        transition: color 0.2s ease, background-color 0.2s ease;
        position: relative;
        display: flex;
        align-items: center;
    }

    .header-user-area a:hover {
        color: var(--header-accent);
        background-color: var(--dropdown-hover-bg);
    }

    .header-user-area a:focus-visible {
        outline: none;
        color: var(--header-accent);
        background-color: var(--dropdown-hover-bg);
        box-shadow: 0 0 0 0.2rem var(--focus-ring-color);
    }

    .header-user-area .header-profile img {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 1px solid var(--header-border);
        display: block;
        object-fit: cover;
    }

    .header-user-area a.header-profile {
        padding: 0;
        background-color: transparent !important;
    }

    .header-user-area a.header-profile:hover img {
        border-color: var(--header-accent);
    }

    .cart-count,
    .messages-unread-count {
        position: absolute;
        top: -4px;
        right: -5px;
        background-color: var(--notification-bg);
        color: var(--notification-text);
        border-radius: 8px;
        padding: 1px 4px;
        font-size: 0.6em;
        font-weight: bold;
        line-height: 1;
        border: 1px solid var(--header-bg); /* Make border match header background */
    }

    .auth-buttons {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .auth-buttons a {
        color: var(--header-text-secondary);
        font-size: 0.85rem;
        font-weight: 500;
        padding: 6px 10px;
        border-radius: var(--button-radius);
        transition: color 0.2s ease, background-color 0.2s ease;
    }

    .auth-buttons a:hover {
        color: var(--header-accent);
        background-color: var(--dropdown-hover-bg);
    }

    .auth-buttons a:focus-visible {
        outline: none;
        color: var(--header-accent);
        background-color: var(--dropdown-hover-bg);
        box-shadow: 0 0 0 0.2rem var(--focus-ring-color);
    }

    /* --- Search --- */
    .search-container {
        position: relative;
    }

    .search-icon-btn {
        color: var(--header-text-secondary);
        font-size: 1.2em;
        padding: 8px;
        border-radius: var(--button-radius);
        transition: color 0.2s ease, background-color 0.2s ease;
        margin-left: 5px;
        cursor: pointer;
    }

    .search-icon-btn:hover,
    .search-container.search-active .search-icon-btn {
        color: var(--header-accent);
        background-color: var(--dropdown-hover-bg);
    }

    .search-icon-btn:focus-visible {
        outline: none;
        color: var(--header-accent);
        background-color: var(--dropdown-hover-bg);
        box-shadow: 0 0 0 0.2rem var(--focus-ring-color);
    }

    .new-search-form {
        display: flex;
        position: absolute;
        right: 0;
        top: 110%; /* Position below the button */
        background-color: var(--search-bg);
        border: 1px solid var(--search-border);
        border-radius: var(--button-radius);
        box-shadow: 0 4px 10px var(--dropdown-shadow);
        width: 300px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: opacity 0.2s ease, transform 0.2s ease, visibility 0s linear 0.2s;
        z-index: 1031;
    }

    .search-container.search-active .new-search-form {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        transition: opacity 0.2s ease, transform 0.2s ease, visibility 0s linear 0s;
    }

    .search-input {
        flex-grow: 1;
        border: none;
        padding: 8px 12px;
        background: none;
        color: var(--header-text);
        font-size: 0.9em;
        outline: none;
        border-radius: var(--button-radius) 0 0 var(--button-radius);
        min-width: 0; /* Fix flexbox shrinking issue */
    }

    .search-input::placeholder {
        color: var(--search-placeholder-text);
    }

    .new-search-button {
        padding: 8px 12px;
        font-size: 0.9em;
        color: var(--header-accent);
        background-color: var(--dropdown-hover-bg);
        border-left: 1px solid var(--search-border);
        border-radius: 0 var(--button-radius) var(--button-radius) 0;
        transition: background-color 0.2s ease;
        flex-shrink: 0;
    }

    .new-search-button:hover {
        background-color: #e2e6ea;
    }

    .new-search-form:focus-within {
        border-color: var(--search-focus-border);
        box-shadow: 0 0 0 0.2rem var(--focus-ring-color), 0 4px 10px var(--dropdown-shadow);
    }

    /* --- Dropdown Base (Desktop) --- */
    .dropdown-menu {
        display: none;
        position: absolute;
        left: 0;
        top: 100%;
        background-color: var(--dropdown-bg);
        min-width: 280px;
        box-shadow: 0 5px 15px var(--dropdown-shadow);
        z-index: 1010;
        border: 1px solid var(--dropdown-border);
        border-radius: 8px;
        padding: 15px;
        margin-top: 4px;
        opacity: 0;
        transform: translateY(5px);
        transition: opacity 0.2s ease, transform 0.2s ease;
    }

    /* Updated Products Dropdown Styles */
    .dropdown-menu.products-dropdown {
        width: 600px;
        max-height: 85vh;
        overflow-y: auto;
        padding: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(5px);
    }

    .nav-item:hover .products-dropdown {
        transform: translateX(-50%) translateY(0);
    }

    .dropdown-section {
        margin-bottom: 25px;
    }

    .dropdown-section:last-child {
        margin-bottom: 0;
    }

    .dropdown-section h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--mega-menu-heading-color);
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--header-border);
    }

    /* Subcategory Grid Layout */
    .subcategory-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 15px;
        padding: 5px;
    }

    .subcategory-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 10px;
        border-radius: 8px;
        transition: all 0.3s ease;
        background-color: var(--dropdown-bg);
        border: 1px solid var(--header-border);
    }

    .subcategory-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        border-color: var(--header-accent);
        background-color: var(--dropdown-hover-bg);
    }

    .subcategory-image-wrapper {
        width: 64px;
        height: 64px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.3s ease;
    }

    .subcategory-image {
        width: 100%;
        height: 100%;
        object-fit: contain;
        border-radius: 6px;
    }

    .subcategory-card:hover .subcategory-image-wrapper {
        transform: scale(1.05);
    }

    .subcategory-name {
        font-size: 0.85rem;
        color: var(--header-text);
        transition: color 0.3s ease;
        line-height: 1.2;
        margin-top: auto;
    }

    .subcategory-card:hover .subcategory-name {
        color: var(--header-accent);
    }

    /* Custom Scrollbar */
    .products-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .products-dropdown::-webkit-scrollbar-track {
        background: var(--dropdown-bg);
    }

    .products-dropdown::-webkit-scrollbar-thumb {
        background: var(--header-text-secondary);
        border-radius: 3px;
    }

    .products-dropdown::-webkit-scrollbar-thumb:hover {
        background: var(--header-accent);
    }

    /* Mobile Styles */
    @media (max-width: 767px) {
        .products-dropdown {
            position: static;
            width: 100%;
            max-height: none;
            border: none;
            box-shadow: none;
            background-color: var(--dropdown-hover-bg);
            margin-top: 0;
            padding: 15px;
            transform: none;
        }

        .subcategory-grid {
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
        }

        .subcategory-image-wrapper {
            width: 48px;
            height: 48px;
        }

        .subcategory-name {
            font-size: 0.8rem;
        }

        .dropdown-section {
            margin-bottom: 20px;
        }

        .dropdown-section h4 {
            font-size: 0.9rem;
            margin-bottom: 12px;
        }
    }

    /* Remove old mega menu styles */
    .mega-menu,
    .mega-menu-content,
    .mega-menu-grid,
    .mega-menu-columns,
    .mega-menu-column {
        display: none;
    }

    /* =========================================== */
    /* --- Responsive Design --- */
    /* =========================================== */

    /* --- Medium Screens (Adjust Mega Menu Width) --- */
    @media (max-width: 1100px) {
        .mega-menu {
            width: 90vw; /* Wider on smaller screens */
            max-width: 800px; /* Limit max width */
            padding: 20px; /* Reduce padding */
        }

        .mega-menu-content {
            gap: 30px; /* Reduce gap */
        }

        .mega-menu-grid {
            flex-basis: 60%; /* Adjust flex basis */
            padding-right: 30px;
            gap: 15px;
        }

        .mega-menu-columns {
            flex-basis: 40%;
            gap: 25px;
        }
    }

    /* --- Tablet Screens (Stack Mega Menu, Adjust Nav) --- */
    @media (max-width: 992px) {
        .main-header-nav {
            padding: 0 15px; /* Reduce padding */
            gap: 10px; /* Reduce gap */
            flex-wrap: nowrap; /* Prevent wrapping before mobile toggle appears */
        }

        .logo {
            margin-right: auto; /* Push logo left, actions right */
        }

        .header-right-actions {
            margin-left: 0; /* Reset margin */
        }

        /* Adjust Mega Menu for Stacking */
        .mega-menu {
            width: 95vw;
            max-width: none; /* Allow full width */
        }

        .mega-menu-content {
            flex-direction: column; /* Stack grid and columns */
            gap: 25px;
        }

        .mega-menu-grid {
            flex-basis: auto; /* Reset basis */
            border-right: none; /* Remove separator */
            padding-right: 0;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); /* Auto-fit columns */
        }

        .mega-menu-columns {
            flex-basis: auto; /* Reset basis */
            flex-direction: row; /* Arrange columns side-by-side */
            flex-wrap: wrap; /* Allow wrapping */
        }

        .mega-menu-column {
            flex-basis: calc(50% - 15px); /* Approx 2 columns */
        }
    }

     /* --- Mobile Screens (Hamburger Menu, Stacked Layout) --- */
     @media (max-width: 767px) {
        .main-header-nav {
            min-height: 50px;
            padding: 0 10px;
            justify-content: space-between; /* Logo left, Toggles/Actions right */
        }

        /* Hide desktop navigation container */
        .category-nav-container {
            display: none; /* Hide initially */
            position: absolute;
            top: 100%; /* Below header */
            left: 0;
            width: 100%;
            background-color: var(--header-bg);
            flex-direction: column;
            align-items: stretch; /* Make items full width */
            padding: 10px 0; /* Vertical padding */
            border-top: 1px solid var(--header-border);
            box-shadow: 0 4px 8px var(--dropdown-shadow);
            max-height: calc(100vh - 60px); /* Limit height, prevent full screen */
            overflow-y: auto; /* Allow scrolling if content overflows */
            z-index: 1029; /* Ensure it's above page content but below header */
        }

        /* Show nav container when body has the active class (added by JS) */
        body.mobile-nav-active .category-nav-container {
            display: flex;
        }

        /* Show mobile toggle button */
        .mobile-menu-toggle {
            display: block;
        }

        .logo img {
            height: 26px; /* Slightly smaller logo */
        }

        /* Mobile Navigation Item Styling */
        .category-nav-container .nav-item,
        .category-nav-container .nav-link {
             width: 100%; /* Ensure items take full width */
        }

        .nav-link,
        .category-btn {
            display: flex; /* Re-apply flex for mobile overrides */
            width: 100%;
            justify-content: flex-start; /* Align icon/text to the left */
            padding: 12px 20px; /* Consistent padding */
            border-bottom: 1px solid var(--header-border);
            border-radius: 0; /* Remove rounding */
            text-align: left;
            /* Ensure background is reset for consistent hover/active */
            background-color: transparent;
        }

        /* Remove border from the very last item in the mobile nav */
        .category-nav-container > *:last-child > a,
        .category-nav-container > *:last-child > button,
        .category-nav-container > a:last-child {
             border-bottom: none;
        }

        /* Ensure dropdown arrow shows and is on the right */
        .nav-link.has-dropdown::after,
        .category-btn.has-dropdown::after {
            display: inline-block; /* Make sure it's visible */
            margin-left: auto; /* Push to far right */
        }

        /* --- Mobile Dropdown Container Styling (Standard and Mega) --- */
        .nav-item .dropdown-menu,
     .nav-item .mega-menu {
         display: none;
         /* --- Explicit Resets for Positioning --- */
         position: static !important; /* Ensure static positioning */
         width: 100%;                 /* Full width */
         left: 0 !important;         /* Reset any 'left' offset from desktop */
         transform: none !important; /* Reset any transforms */
         margin-left: 0 !important;  /* Reset horizontal margin */
         margin-right: 0 !important; /* Reset horizontal margin */
         max-width: 100%;            /* Prevent exceeding parent width */
         float: none;                /* Clear any floats */
         /* --- End Resets --- */

         padding: 0; /* Container should have no padding itself */
         box-shadow: none;
         border: none;
         border-top: 1px solid var(--header-border); /* Separator line */
         background-color: var(--dropdown-hover-bg);
         box-sizing: border-box; /* Ensure width includes border/padding if any were added */
     }

     .nav-item .mega-menu .mega-menu-content {
        /* ... existing styles (flex-direction, gap, padding) ... */
        flex-direction: column;
        gap: 25px;
        padding: 20px 15px;
        /* --- Add explicit width/box-sizing --- */
        width: 100%;
        box-sizing: border-box;
        overflow-x: hidden; /* Prevent horizontal scroll within content if something unexpected happens */
    }
        /* Show dropdown when parent has active class (toggled by JS) */
        .nav-item.mobile-dropdown-active > .dropdown-menu,
        .nav-item.mobile-dropdown-active > .mega-menu {
            display: block;
        }

        /* Rotate arrow when dropdown is active */
        .nav-item.mobile-dropdown-active > .has-dropdown::after {
            transform: rotate(180deg);
        }

        /* Indent standard dropdown links for hierarchy */
        .nav-item .dropdown-menu a {
             padding-left: 35px;
        }


        /* --- Mobile Mega Menu Specific Styling --- */

        /* Add scrollable container for mobile mega menu */
        .nav-item .mega-menu {
            max-height: 80vh; /* Set maximum height to 80% of viewport height */
            overflow-y: auto; /* Enable vertical scrolling */
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
        }

        /* Add padding inside the content area */
        .nav-item .mega-menu .mega-menu-content {
            flex-direction: column; /* Stack grid and columns */
            gap: 25px; /* Space between grid and columns section */
            padding: 20px 15px; /* Inner padding (Top/Bottom, Left/Right) */
            max-height: none; /* Remove any height restrictions */
            overflow: visible; /* Allow content to be visible */
        }

        /* Custom scrollbar styling for better mobile experience */
        .nav-item .mega-menu::-webkit-scrollbar {
            width: 6px;
        }

        .nav-item .mega-menu::-webkit-scrollbar-track {
            background: var(--header-bg);
        }

        .nav-item .mega-menu::-webkit-scrollbar-thumb {
            background: var(--header-text-secondary);
            border-radius: 3px;
        }

        .nav-item .mega-menu::-webkit-scrollbar-thumb:hover {
            background: var(--header-accent);
        }

        /* Grid of hardware items */
        .nav-item .mega-menu .mega-menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); /* Auto-fit columns */
            gap: 15px;
            padding: 0;
            border: none;
            max-height: none; /* Remove height restriction */
            overflow: visible; /* Allow content to be visible */
        }

        .nav-item .mega-menu .mega-menu-grid-item {
            display: block;
            background-color: var(--mega-menu-grid-bg);
            border: 1px solid var(--header-border);
            border-radius: var(--button-radius);
            text-align: center;
            padding: 15px 8px;
            transition: background-color 0.2s ease;
            height: auto; /* Allow height to adjust to content */
        }

        .nav-item .mega-menu .mega-menu-grid-item img {
            display: block;
            max-width: 60px;
            height: 60px;
            object-fit: contain;
            margin: 0 auto 10px auto;
        }

        .nav-item .mega-menu .mega-menu-grid-item span {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--header-text);
            display: block;
            line-height: 1.3;
            white-space: normal; /* Allow text to wrap */
        }

        /* Columns containing lists (Peripherals, Expansion, etc.) */
        .nav-item .mega-menu .mega-menu-columns {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 0;
            max-height: none; /* Remove height restriction */
            overflow: visible; /* Allow content to be visible */
        }

        .nav-item .mega-menu .mega-menu-column {
            flex-basis: auto;
            max-height: none; /* Remove height restriction */
            overflow: visible; /* Allow content to be visible */
        }

        .nav-item .mega-menu .mega-menu-column h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--mega-menu-heading-color);
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid var(--header-border);
        }

        .nav-item .mega-menu .mega-menu-column ul {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding-left: 10px; /* Indent list items */
            max-height: none; /* Remove height restriction */
            overflow: visible; /* Allow content to be visible */
        }

        .nav-item .mega-menu .mega-menu-column ul li a {
            color: var(--header-text);
            padding: 6px 0;
            font-size: 0.85rem;
            display: block;
            transition: color 0.15s ease;
            position: relative;
            background-color: transparent !important;
            white-space: normal; /* Allow text to wrap */
        }

         .nav-item .mega-menu .mega-menu-column ul li a:hover,
         .nav-item .mega-menu .mega-menu-column ul li a:focus-visible {
             color: var(--header-accent);
         }

         /* Optional: Style or hide the ::before arrow on mobile list items */
         .nav-item .mega-menu .mega-menu-column ul li a::before {
             /* display: none; */ /* Uncomment to hide */
             content: '\f105';
             font-family: 'Font Awesome 6 Free';
             font-weight: 900;
             font-size: 0.7em;
             color: var(--header-text-secondary);
             position: absolute;
             left: -5px;
             top: 50%;
             transform: translateY(-50%);
             opacity: 0.5;
             transition: color 0.15s ease, opacity 0.15s ease;
         }
         .nav-item .mega-menu .mega-menu-column ul li a:hover::before {
              color: var(--header-accent);
              opacity: 1;
          }

        /* --- Mobile Hover/Active States for Nav Items --- */
        /* Disable general hover background */
        .nav-link:hover,
        .category-btn:hover {
            background-color: transparent;
        }
        /* Style for tap feedback and active dropdown trigger */
        .nav-link:active,
        .category-btn:active,
        .nav-item.mobile-dropdown-active > .category-btn,
        .nav-item.mobile-dropdown-active > .nav-link {
            background-color: var(--dropdown-hover-bg);
            color: var(--header-accent);
        }
        /* Ensure icons also change color */
        .nav-item.mobile-dropdown-active > .category-btn .fa,
        .nav-item.mobile-dropdown-active > .nav-link .fa,
        .nav-item.mobile-dropdown-active > .category-btn .fas,
        .nav-item.mobile-dropdown-active > .nav-link .fas {
            color: var(--header-accent);
        }

         /* Prevent desktop hover logic on touch devices */
         @media (hover: none) {
             .nav-item:hover > .dropdown-menu,
             .nav-item:hover > .mega-menu {
                 display: none; /* Prevent hover from showing dropdowns */
             }
             /* Ensure active dropdown stays visible even during scroll/touch */
             .nav-item.mobile-dropdown-active:hover > .dropdown-menu,
             .nav-item.mobile-dropdown-active:hover > .mega-menu {
                  display: block;
             }
         }

        /* --- Mobile Search Form Adjustments --- */
        .new-search-form {
            width: calc(100vw - 40px); /* Adjust width */
            right: -10px; /* Position relative to edge */
            top: calc(100% + 5px); /* Position below icons */
        }
        .search-container.search-active .new-search-form {
            transform: translateY(0); /* Simpler transform */
        }

        /* --- Mobile User Area Adjustments --- */
        .header-right-actions {
            margin-left: 0; /* Align actions to the right edge */
        }
        .header-user-area {
            flex-wrap: nowrap; /* Prevent icons wrapping */
            gap: 5px; /* Reduce gap */
            justify-content: flex-end;
        }
        .auth-buttons a {
            font-size: 0.8rem;
            padding: 5px 8px;
        }

    } /* End of @media (max-width: 767px) */

    /* Add styles for subcategory images in the mega menu */
    .mega-menu-column ul li a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
    }

    .mega-menu-column ul li a .subcategory-image {
        width: 30px;
        height: 30px;
        object-fit: contain;
        border-radius: 4px;
    }

    .mega-menu-column ul li a span {
        flex: 1;
    }

    /* Mobile styles */
    @media (max-width: 767px) {
        .mega-menu-column ul li a {
            padding: 10px 0;
        }
        
        .mega-menu-column ul li a .subcategory-image {
            width: 25px;
            height: 25px;
        }
    }

    /* Products Dropdown Animation */
    .products-dropdown {
        display: none;
        opacity: 0;
        transition: opacity 0.2s ease, transform 0.2s ease;
    }

    /* Show dropdown on hover for desktop */
    @media (min-width: 768px) {
        .nav-item:hover .products-dropdown {
            opacity: 1;
        }
    }

    /* Mobile dropdown active state */
    @media (max-width: 767px) {
        .nav-item.mobile-dropdown-active .products-dropdown {
            opacity: 1;
        }
    }

    /* --- Mobile Menu Toggle Button --- */
    .mobile-menu-toggle {
        display: none; /* Hidden by default on desktop */
        padding: 8px;
        color: var(--header-text);
        font-size: 1.2em;
        border-radius: var(--button-radius);
        transition: color 0.2s ease, background-color 0.2s ease;
        margin-right: 10px;
    }

    .mobile-menu-toggle:hover {
        color: var(--header-accent);
        background-color: var(--dropdown-hover-bg);
    }

    /* --- Mobile Styles --- */
    @media (max-width: 767px) {
        .mobile-menu-toggle {
            display: block;
            order: 1;
        }

        .logo {
            order: 0;
        }

        .header-right-actions {
            order: 2;
        }

        .main-header-nav {
            padding: 10px;
            justify-content: space-between;
            position: relative;
            gap: 5px;
        }

        /* Mobile Navigation Container */
        .category-nav-container {
            display: none;
            position: fixed;
            top: 60px; /* Height of header */
            left: 0;
            width: 100%;
            height: calc(100vh - 60px);
            background-color: var(--header-bg);
            padding: 0;
            flex-direction: column;
            overflow-y: auto;
            z-index: 1000;
            border-top: 1px solid var(--header-border);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        body.mobile-nav-active .category-nav-container {
            display: flex;
        }

        /* Mobile Navigation Items */
        .nav-link,
        .category-btn {
            padding: 15px 20px;
            width: 100%;
            border-bottom: 1px solid var(--header-border);
            display: flex;
            align-items: center;
            justify-content: flex-start;
            font-size: 1rem;
        }

        .nav-link i,
        .category-btn i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }

        /* Products Dropdown Mobile */
        .products-dropdown {
            position: static;
            width: 100%;
            transform: none !important;
            box-shadow: none;
            border: none;
            margin: 0;
            padding: 0;
            max-height: none;
            background-color: var(--dropdown-hover-bg);
        }

        .dropdown-section {
            margin: 0;
            padding: 15px;
            border-bottom: 1px solid var(--header-border);
        }

        .dropdown-section h4 {
            font-size: 1rem;
            margin-bottom: 15px;
            color: var(--header-text);
        }

        .subcategory-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 0;
        }

        .subcategory-card {
            padding: 10px;
            background-color: var(--header-bg);
        }

        .subcategory-image-wrapper {
            width: 50px;
            height: 50px;
        }

        .subcategory-name {
            font-size: 0.8rem;
            margin-top: 5px;
        }

        /* Mobile Header Actions */
        .header-right-actions {
            gap: 8px;
        }

        .header-user-area {
            gap: 5px;
        }

        .header-user-area a {
            padding: 8px;
        }

        .auth-buttons {
            display: none; /* Hide on mobile, show in nav */
        }

        /* Mobile Search */
        .new-search-form {
            position: fixed;
            top: 60px;
            left: 0;
            width: 100%;
            padding: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform: translateY(-100%);
            transition: transform 0.3s ease;
        }

        .search-container.search-active .new-search-form {
            transform: translateY(0);
        }

        /* Animation for dropdown */
        .nav-item .products-dropdown {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-item.mobile-dropdown-active .products-dropdown {
            display: block;
            opacity: 1;
        }
    }

    /* Prevent body scroll when mobile menu is open */
    body.mobile-nav-active {
        overflow: hidden;
    }

    /* Dark mode adjustments for mobile */
    @media (max-width: 767px) {
        [data-theme="dark"] .category-nav-container {
            background-color: var(--header-bg);
        }

        [data-theme="dark"] .subcategory-card {
            background-color: rgba(255, 255, 255, 0.05);
        }
    }

    /* --- Completed Builds Dropdown --- */
    .dropdown-menu#builds-dropdown {
        min-width: 200px;
        padding: 10px 0;
        display: none;
        opacity: 0;
        transition: opacity 0.2s ease, transform 0.2s ease;
    }

    .dropdown-menu#builds-dropdown a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 20px;
        color: var(--header-text);
        font-size: 0.9rem;
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .dropdown-menu#builds-dropdown a:hover {
        background-color: var(--dropdown-hover-bg);
        color: var(--header-accent);
    }

    .dropdown-menu#builds-dropdown a i {
        width: 20px;
        text-align: center;
        color: var(--header-text-secondary);
    }

    .dropdown-menu#builds-dropdown a:hover i {
        color: var(--header-accent);
    }

    /* Show dropdown on hover for desktop */
    @media (min-width: 768px) {
        .nav-item:hover .dropdown-menu#builds-dropdown {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Mobile styles for builds dropdown */
    @media (max-width: 767px) {
        .dropdown-menu#builds-dropdown {
            position: static;
            width: 100%;
            box-shadow: none;
            border: none;
            background-color: var(--dropdown-hover-bg);
            margin: 0;
            padding: 0;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .dropdown-menu#builds-dropdown a {
            padding: 15px 20px 15px 35px;
            border-bottom: 1px solid var(--header-border);
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--header-text);
            font-size: 0.95rem;
        }

        .dropdown-menu#builds-dropdown a:last-child {
            border-bottom: none;
        }

        .dropdown-menu#builds-dropdown a i {
            width: 20px;
            text-align: center;
            color: var(--header-text-secondary);
            font-size: 1em;
        }

        .nav-item.mobile-dropdown-active .dropdown-menu#builds-dropdown {
            display: block;
            opacity: 1;
        }

        .dropdown-menu#builds-dropdown a:active,
        .dropdown-menu#builds-dropdown a:hover {
            background-color: var(--dropdown-hover-bg);
            color: var(--header-accent);
        }

        .dropdown-menu#builds-dropdown a:active i,
        .dropdown-menu#builds-dropdown a:hover i {
            color: var(--header-accent);
        }

        /* Ensure proper spacing in mobile menu */
        .nav-item {
            width: 100%;
        }

        .nav-item .category-btn {
            width: 100%;
            justify-content: flex-start;
            padding: 15px 20px;
            font-size: 1rem;
            border-bottom: 1px solid var(--header-border);
        }

        .nav-item .category-btn i {
            width: 20px;
            text-align: center;
            margin-right: 12px;
        }

        /* Animation for dropdown arrow */
        .nav-item .category-btn.has-dropdown::after {
            transition: transform 0.3s ease;
        }

        .nav-item.mobile-dropdown-active .category-btn.has-dropdown::after {
            transform: rotate(180deg);
        }
    }

    /* Mobile Left Actions */
    .mobile-left-actions {
        display: none;
    }

    @media (max-width: 767px) {
        .mobile-left-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            order: 0;
        }

        .mobile-cart-link {
            color: var(--header-text-secondary);
            font-size: 1.1em;
            padding: 8px;
            position: relative;
        }

        /* Hide cart in regular header on mobile */
        .header-user-area .fa-shopping-cart {
            display: none;
        }

        /* Mobile User Profile Section */
        .mobile-user-section {
            padding: 15px 20px;
            border-bottom: 1px solid var(--header-border);
            display: flex;
            align-items: center;
        }

        .mobile-profile-link {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
        }

        .mobile-profile-link img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .mobile-profile-link span {
            font-size: 1rem;
            font-weight: 500;
            color: var(--header-text);
        }

        /* Mobile Navigation Links */
        .mobile-nav-links {
            border-top: 1px solid var(--header-border);
            margin-top: 10px;
            padding-top: 10px;
        }

        .mobile-nav-links .nav-link,
        .mobile-auth-buttons .nav-link {
            padding: 15px 20px;
            border-bottom: 1px solid var(--header-border);
        }

        .mobile-auth-buttons {
            border-top: 1px solid var(--header-border);
            margin-top: 10px;
            padding-top: 10px;
        }

        /* Adjust main header layout */
        .main-header-nav {
            justify-content: space-between;
        }

        .logo {
            order: 1;
        }

        .header-right-actions {
            order: 2;
        }
    }

    /* Mobile Elements (Hidden on Desktop) */
    .mobile-left-actions,
    .mobile-menu-toggle,
    .mobile-user-section,
    .mobile-nav-links,
    .mobile-auth-buttons,
    .mobile-logout,
    .mobile-cart-link {
        display: none;
    }

    /* Show mobile elements only on mobile devices */
    @media (max-width: 767px) {
        /* Show mobile menu elements */
        .mobile-left-actions,
        .mobile-menu-toggle,
        .mobile-user-section,
        .mobile-nav-links,
        .mobile-auth-buttons,
        .mobile-logout {
            display: flex;
        }

        .mobile-cart-link {
            display: inline-flex;
        }

        /* Hide desktop elements */
        .header-user-area {
            display: none !important;
        }

        /* Mobile Navigation Container */
        .category-nav-container {
            display: none;
            position: fixed;
            top: 60px;
            left: 0;
            width: 100%;
            height: calc(100vh - 60px);
            background-color: var(--header-bg);
            padding: 0;
            flex-direction: column;
            overflow-y: auto;
            z-index: 1000;
            border-top: 1px solid var(--header-border);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Show navigation when mobile menu is active */
        body.mobile-nav-active .category-nav-container {
            display: flex;
        }

        /* Adjust header layout for mobile */
        .main-header-nav {
            justify-content: space-between;
        }

        .logo {
            order: 1;
        }

        .mobile-left-actions {
            order: 0;
        }

        .header-right-actions {
            order: 2;
        }
    }

    /* Desktop Navigation (Hidden on Mobile) */
    @media (min-width: 768px) {
        .category-nav-container {
            display: flex !important;
            position: static;
            width: auto;
            height: auto;
            background: none;
            box-shadow: none;
            border: none;
            overflow: visible;
            padding: 0;
            flex-direction: row;
        }

        .nav-item,
        .nav-link {
            width: auto;
        }

        .nav-link,
        .category-btn {
            border: none;
            padding: 10px 12px;
        }
    }
</style>
    </style>
</head>
<body> <?php if (isset($_SESSION['loggedin']) && !$emailVerified && isset($pdo)): ?>
    <div class="email-verification-banner">
        Your email is not verified. Please <a href="profile.php">verify your email</a>.
    </div>
    <?php endif; ?>
    <?php if (isset($settings['maintenance_mode']) && intval($settings['maintenance_mode']) === 1): ?>
    <div class="warning" style="background: red; color: white; padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center;">
        <strong>Maintenance Mode Active</strong> 
    </div>
<?php endif; ?>

    <header>
        <div class="main-header-nav">
            <!-- Cart and Mobile Menu (Left Side) -->
            <div class="mobile-left-actions">
                <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                <a href="cart.php" class="mobile-cart-link" title="Cart" aria-label="Shopping Cart <?php echo $cartCount > 0 ? '(' . $cartCount . ' items)' : ''; ?>">
                    <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-count"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <button class="mobile-menu-toggle" aria-label="Toggle Navigation" aria-expanded="false">
                    <i class="fas fa-bars" aria-hidden="true"></i>
                </button>
            </div>

            <div class="logo">
                <a href="index.php"><img src="<?= htmlspecialchars($settings['logo'] ?? 'logo (1) text.png') ?>" alt="<?= htmlspecialchars($settings['site_name'] ?? 'EcoTech') ?> Logo"></a>
            </div>

            <nav class="category-nav-container" id="main-navigation">
                <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                <!-- Mobile User Profile Section -->
                <div class="mobile-user-section">
                    <a href="profile.php" class="mobile-profile-link">
                        <img src="<?php echo $user['profile_picture'] ? 'uploads/profiles/' . htmlspecialchars($user['profile_picture']) : 'https://i.top4top.io/p_3273sk4691.jpg'; ?>" alt="Profile Picture">
                        <span>My Profile</span>
                    </a>
                </div>

                <!-- Mobile Navigation Links -->
                <div class="mobile-nav-links">
                    <?php if ($isAdmin): ?>
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                        <span>Admin Dashboard</span>
                    </a>
                    <?php endif; ?>

                    <a href="marketplace.php" class="nav-link">
                        <i class="fas fa-store" aria-hidden="true"></i>
                        <span>Marketplace</span>
                    </a>

                    <a href="inbox.php" class="nav-link">
                        <i class="fab fa-facebook-messenger" aria-hidden="true"></i>
                        <span>Messages</span>
                        <?php if ($unreadCount > 0): ?>
                            <span class="messages-unread-count"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="cart.php" class="nav-link">
                        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                        <span>Cart</span>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-count"><?php echo $cartCount; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="wishlist.php" class="nav-link">
                        <i class="fas fa-heart" aria-hidden="true"></i>
                        <span>Wishlist</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Main Navigation Links -->
                <a href="buildyourpc.php" class="nav-link">
                    <i class="fas fa-tools" aria-hidden="true"></i>
                    <span>Builder</span>
                </a>

                <a href="recycle.php" class="nav-link">
                    <i class="fa-solid fa-recycle" aria-hidden="true"></i>
                    <span>Recycle</span>
                </a>

                <!-- Completed Builds Dropdown -->
                <div class="nav-item">
                    <button class="category-btn has-dropdown" aria-expanded="false" aria-controls="builds-dropdown">
                        <i class="fas fa-desktop" aria-hidden="true"></i>
                        <span>Completed Builds</span>
                    </button>
                    <div class="dropdown-menu" id="builds-dropdown" role="menu">
                        <a href="subcategory.php?id=22" role="menuitem">
                            <i class="fas fa-building" aria-hidden="true"></i>
                            <span>Office PCs</span>
                        </a>
                        <a href="subcategory.php?id=21" role="menuitem">
                            <i class="fas fa-gamepad" aria-hidden="true"></i>
                            <span>Gaming PCs</span>
                        </a>
                        <a href="subcategory.php?id=24" role="menuitem">
                            <i class="fas fa-laptop-code" aria-hidden="true"></i>
                            <span>Gaming Laptops</span>
                        </a>
                        <a href="subcategory.php?id=23" role="menuitem">
                            <i class="fas fa-box" aria-hidden="true"></i>
                            <span>Compact PCs</span>
                        </a>
                        <a href="subcategory.php?id=25" role="menuitem">
                            <i class="fas fa-laptop" aria-hidden="true"></i>
                            <span>Ultrabooks</span>
                        </a>
                    </div>
                </div>
                
                <!-- Products Dropdown -->
                <div class="nav-item">
                    <button class="category-btn has-dropdown" aria-expanded="false">
                        <i class="fas fa-box-open" aria-hidden="true"></i>
                        <span>Products</span>
                    </button>
                    <div class="dropdown-menu products-dropdown" role="menu">
                        <!-- Hardware Components -->
                        <div class="dropdown-section">
                            <h4>Hardware Components</h4>
                            <div class="subcategory-grid">
                            <?php 
                            // Define the specific subcategory IDs for hardware components
                            $hardwareSubcatIds = [1, 2, 3, 4, 5, 6, 7, 8]; // Update with your actual subcategory IDs
                            
                            if (!empty($groupedSubcategories['grid'])): 
                                foreach ($hardwareSubcatIds as $subcatId):
                                    if (isset($groupedSubcategories['grid'][$subcatId])): 
                                        $item = $groupedSubcategories['grid'][$subcatId];
                            ?>
                                    <a href="<?php echo htmlspecialchars($item['link']); ?>" class="subcategory-card">
                                        <div class="subcategory-image-wrapper">
                                            <img src="<?php echo !empty($item['image']) ? htmlspecialchars($item['image']) : 'https://placehold.co/100x100/e2e8f0/64748b?text=N/A'; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="subcategory-image"
                                                 onerror="this.src='https://placehold.co/100x100/e2e8f0/64748b?text=N/A'"
                                                 loading="lazy">
                                        </div>
                                        <span class="subcategory-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                    </a>
                            <?php 
                                    endif;
                                endforeach;
                            endif; 
                            ?>
                            </div>
                        </div>

                        <!-- Peripherals -->
                        <div class="dropdown-section">
                                    <h4>Peripherals</h4>
                            <div class="subcategory-grid">
                                        <?php if(!empty($groupedSubcategories['peripherals'])): ?>
                                            <?php foreach ($groupedSubcategories['peripherals'] as $subcat): ?>
                                    <a href="subcategory.php?id=<?php echo $subcat['id']; ?>" class="subcategory-card">
                                        <div class="subcategory-image-wrapper">
                                            <img src="<?php echo !empty($subcat['image_path']) ? htmlspecialchars($subcat['image_path']) : 'https://placehold.co/100x100/e2e8f0/64748b?text=N/A'; ?>" 
                                                             alt="<?php echo htmlspecialchars($subcat['name']); ?>"
                                                 class="subcategory-image"
                                                 onerror="this.src='https://placehold.co/100x100/e2e8f0/64748b?text=N/A'">
                                        </div>
                                        <span class="subcategory-name"><?php echo htmlspecialchars($subcat['name']); ?></span>
                                    </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                            </div>
                        </div>

                        <!-- Expansion & Networking -->
                        <div class="dropdown-section">
                            <h4>Expansion & Networking</h4>
                            <div class="subcategory-grid">
                            <?php if(!empty($groupedSubcategories['expansion'])): ?>
                                <?php foreach ($groupedSubcategories['expansion'] as $subcat): ?>
                                    <a href="subcategory.php?id=<?php echo $subcat['id']; ?>" class="subcategory-card">
                                        <div class="subcategory-image-wrapper">
                                            <img src="<?php echo !empty($subcat['image_path']) ? htmlspecialchars($subcat['image_path']) : 'https://placehold.co/100x100/e2e8f0/64748b?text=N/A'; ?>" 
                                                 alt="<?php echo htmlspecialchars($subcat['name']); ?>"
                                                 class="subcategory-image"
                                                 onerror="this.src='https://placehold.co/100x100/e2e8f0/64748b?text=N/A'">
                                        </div>
                                        <span class="subcategory-name"><?php echo htmlspecialchars($subcat['name']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                        </div>

                        <!-- Software -->
                        <?php if (!empty($groupedSubcategories['software'])): ?>
                        <div class="dropdown-section">
                            <h4>Software</h4>
                            <div class="subcategory-grid">
                            <?php foreach ($groupedSubcategories['software'] as $subcat): ?>
                                <a href="subcategory.php?id=<?php echo $subcat['id']; ?>" class="subcategory-card">
                                    <div class="subcategory-image-wrapper">
                                        <img src="<?php echo !empty($subcat['image_path']) ? htmlspecialchars($subcat['image_path']) : 'https://placehold.co/100x100/e2e8f0/64748b?text=N/A'; ?>" 
                                             alt="<?php echo htmlspecialchars($subcat['name']); ?>"
                                             class="subcategory-image"
                                             onerror="this.src='https://placehold.co/100x100/e2e8f0/64748b?text=N/A'">
                                    </div>
                                    <span class="subcategory-name"><?php echo htmlspecialchars($subcat['name']); ?></span>
                                </a>
                            <?php endforeach; ?>
                            </div>
                        </div>
                                    <?php endif; ?>

                        <!-- Accessories -->
                        <?php if (!empty($groupedSubcategories['accessories'])): ?>
                        <div class="dropdown-section">
                            <h4>Accessories</h4>
                            <div class="subcategory-grid">
                            <?php foreach ($groupedSubcategories['accessories'] as $subcat): ?>
                                <a href="subcategory.php?id=<?php echo $subcat['id']; ?>" class="subcategory-card">
                                    <div class="subcategory-image-wrapper">
                                        <img src="<?php echo !empty($subcat['image_path']) ? htmlspecialchars($subcat['image_path']) : 'https://placehold.co/100x100/e2e8f0/64748b?text=N/A'; ?>" 
                                             alt="<?php echo htmlspecialchars($subcat['name']); ?>"
                                             class="subcategory-image"
                                             onerror="this.src='https://placehold.co/100x100/e2e8f0/64748b?text=N/A'">
                                </div>
                                    <span class="subcategory-name"><?php echo htmlspecialchars($subcat['name']); ?></span>
                                </a>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true): ?>
                <!-- Auth Buttons for Mobile -->
                <div class="mobile-auth-buttons">
                    <a href="login.php" class="nav-link">
                        <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                        <span>Log In</span>
                    </a>
                    <a href="sign-up.php" class="nav-link">
                        <i class="fas fa-user-plus" aria-hidden="true"></i>
                        <span>Register</span>
                    </a>
                </div>
                <?php else: ?>
                <!-- Logout Link -->
                <a href="logout.php" class="nav-link mobile-logout">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    <span>Logout</span>
                </a>
                <?php endif; ?>
            </nav>

            <div class="header-right-actions">
                <div class="header-user-area">
                    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($pdo)): ?>
                        <?php if ($isAdmin): ?> <a href="dashboard.php" title="Admin Dashboard" aria-label="Admin Dashboard"><i class="fas fa-tachometer-alt" aria-hidden="true"></i></a> <?php endif; ?>
                        <a href="marketplace.php" title="Marketplace" aria-label="Marketplace"><i class="fas fa-store" aria-hidden="true"></i></a>
                        <a href="inbox.php" title="Messages" aria-label="Messages <?php echo $unreadCount > 0 ? '(' . $unreadCount . ' unread)' : ''; ?>"><i class="fab fa-facebook-messenger" aria-hidden="true"></i><?php if ($unreadCount > 0): ?><span class="messages-unread-count"><?php echo $unreadCount; ?></span><?php endif; ?></a>
                        <a href="cart.php" title="Cart" aria-label="Shopping Cart <?php echo $cartCount > 0 ? '(' . $cartCount . ' items)' : ''; ?>"><i class="fas fa-shopping-cart" aria-hidden="true"></i><?php if ($cartCount > 0): ?><span class="cart-count"><?php echo $cartCount; ?></span><?php endif; ?></a>
                            <a href="wishlist.php" style="transition: color 0.3s ease;" onmouseover="this.style.color='red'" onmouseout="this.style.color=''" ><i class="fas fa-heart"></i></a>
                        <a href="logout.php" title="Logout" aria-label="Logout"><i class="fas fa-sign-out-alt" aria-hidden="true"style="transition: color 0.3s ease;" onmouseover="this.style.color='red'" onmouseout="this.style.color=''"></i></a>
                        <a href="profile.php" class="header-profile" title="Profile" aria-label="View Profile"><img src="<?php 
                        if ($user['profile_picture']) {
                            echo 'uploads/profiles/' . htmlspecialchars($user['profile_picture']);
                        } else {
                            echo 'https://i.top4top.io/p_3273sk4691.jpg';
                        }
                    ?>" alt="Profile Picture"></a>
                    <?php elseif (!isset($pdo)): ?>
                         <span style="color: var(--notification-bg); font-size: 0.8em; padding: 5px;">DB Err</span>
                    <?php else: ?>
                        <div class="auth-buttons">
                            <a href="login.php">Log In</a>
                            <a href="sign-up.php">Register</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="search-container" id="search-container">
                     <form action="search.php" method="GET" class="new-search-form" id="search-form" role="search"> <input type="text" name="query" class="search-input" placeholder="Search..." aria-label="Search Website"> <button type="submit" class="new-search-button">Go</button>
                    </form>
                    <button type="button" class="search-icon-btn" id="search-icon-btn" title="Search" aria-label="Open Search Form" aria-expanded="false" aria-controls="search-form"> <i class="fas fa-search" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>
                    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const body = document.body;
            const navContainer = document.querySelector('.category-nav-container');
            const searchIconBtn = document.getElementById('search-icon-btn');
            const searchContainer = document.getElementById('search-container');
            const searchForm = document.getElementById('search-form');
            const searchInput = searchForm.querySelector('.search-input');

            // Function to check if we're on mobile view
            const isMobileView = () => window.innerWidth <= 767;

            // Close dropdowns when clicking outside
            document.addEventListener('click', (event) => {
                if (!event.target.closest('.nav-item') && !event.target.closest('.mobile-menu-toggle')) {
                    const activeDropdowns = document.querySelectorAll('.mobile-dropdown-active');
                    activeDropdowns.forEach(item => {
                        item.classList.remove('mobile-dropdown-active');
                        const btn = item.querySelector('.category-btn');
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                    });
                }
            });

            // Handle all dropdown buttons
            const dropdownButtons = document.querySelectorAll('.category-btn.has-dropdown');
            dropdownButtons.forEach(button => {
                button.addEventListener('click', (event) => {
                    if (isMobileView()) {
                        event.preventDefault();
                        event.stopPropagation();
                        
                        const navItem = button.closest('.nav-item');
                        const wasActive = navItem.classList.contains('mobile-dropdown-active');
                        
                        // Close other open dropdowns
                        dropdownButtons.forEach(otherBtn => {
                            const otherNavItem = otherBtn.closest('.nav-item');
                            if (otherNavItem !== navItem) {
                                otherNavItem.classList.remove('mobile-dropdown-active');
                                otherBtn.setAttribute('aria-expanded', 'false');
                            }
                        });

                        // Toggle current dropdown
                        navItem.classList.toggle('mobile-dropdown-active');
                        button.setAttribute('aria-expanded', !wasActive);
                    }
                });

                // Add hover functionality for desktop
                if (!isMobileView()) {
                    const navItem = button.closest('.nav-item');
                    const dropdown = navItem.querySelector('.products-dropdown');
                    
                    if (dropdown) {
                        navItem.addEventListener('mouseenter', () => {
                            dropdown.style.display = 'block';
                            button.setAttribute('aria-expanded', 'true');
                        });
                        
                        navItem.addEventListener('mouseleave', () => {
                            dropdown.style.display = 'none';
                            button.setAttribute('aria-expanded', 'false');
                        });
                    }
                }
            });

            // Mobile menu toggle
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const isExpanded = body.classList.toggle('mobile-nav-active');
                    mobileMenuToggle.setAttribute('aria-expanded', isExpanded);
                    
                    // Close search when opening menu
                    if (isExpanded && searchContainer.classList.contains('search-active')) {
searchContainer.classList.remove('search-active');
                        searchIconBtn.setAttribute('aria-expanded', 'false');
                    }

                    // Close all dropdowns when closing menu
                    if (!isExpanded) {
                        const activeDropdowns = document.querySelectorAll('.mobile-dropdown-active');
                        activeDropdowns.forEach(item => {
                            item.classList.remove('mobile-dropdown-active');
                            const btn = item.querySelector('.category-btn');
                            if (btn) btn.setAttribute('aria-expanded', 'false');
                        });
                    }
                });
            }

            // Search functionality
            if (searchIconBtn && searchContainer) {
                searchIconBtn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const isExpanded = searchContainer.classList.toggle('search-active');
                    searchIconBtn.setAttribute('aria-expanded', isExpanded);

                    if (isExpanded) {
                        searchInput.focus();
                        // Close mobile menu if open
                        if (body.classList.contains('mobile-nav-active')) {
                            body.classList.remove('mobile-nav-active');
                            mobileMenuToggle.setAttribute('aria-expanded', 'false');
                        }
                    }
                });

                // Close search when clicking outside
                document.addEventListener('click', (event) => {
                    if (!searchContainer.contains(event.target) && !searchIconBtn.contains(event.target)) {
                        searchContainer.classList.remove('search-active');
                        searchIconBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            // Handle window resize
            window.addEventListener('resize', () => {
                if (!isMobileView()) {
                    // Reset mobile-specific classes and states when switching to desktop
                    body.classList.remove('mobile-nav-active');
                    document.querySelectorAll('.mobile-dropdown-active').forEach(item => {
                        item.classList.remove('mobile-dropdown-active');
                    });
                }
            });
        });
    </script>
    


<?php if (!empty($settings['footer_scripts'])): ?>
    <?= $settings['footer_scripts'] ?>
<?php endif; ?>
</body>
</html>