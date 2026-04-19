<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

require_permission('reservation.create', '../login.php');

$user_id = (int)$_SESSION['user_id'];
$message = "";
$success = "";
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

// Handle add to cart (with variation support)
if (isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $variation_id = isset($_POST['variation_id']) && $_POST['variation_id'] !== '' ? (int)$_POST['variation_id'] : null;
    
    if ($quantity < 1) $quantity = 1;
    
    // Check product is available
    $check = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_available = 1");
    $check->bind_param("i", $product_id);
    $check->execute();
    $check_result = $check->get_result();
    
    if ($check_result->num_rows > 0) {
        $product = $check_result->fetch_assoc();
        $check->close();
        
        // Get variation label if variation selected
        $variation_label = null;
        if ($variation_id) {
            $var_stmt = $conn->prepare("SELECT variation_label FROM product_variations WHERE id = ? AND product_id = ?");
            $var_stmt->bind_param("ii", $variation_id, $product_id);
            $var_stmt->execute();
            $var_res = $var_stmt->get_result();
            if ($var_res->num_rows > 0) {
                $variation_label = $var_res->fetch_assoc()['variation_label'];
            }
            $var_stmt->close();
        }
        
        // Check if already in cart with same variation
        if ($variation_id) {
            $cart_check = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ? AND variation_id = ?");
            $cart_check->bind_param("iii", $user_id, $product_id, $variation_id);
        } else {
            $cart_check = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ? AND (variation_id IS NULL OR variation_id = 0)");
            $cart_check->bind_param("ii", $user_id, $product_id);
        }
        $cart_check->execute();
        $cart_result = $cart_check->get_result();
        
        if ($cart_result->num_rows > 0) {
            $cart_check->close();
            // Update quantity
            if ($variation_id) {
                $update = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ? AND variation_id = ?");
                $update->bind_param("iiii", $quantity, $user_id, $product_id, $variation_id);
            } else {
                $update = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ? AND (variation_id IS NULL OR variation_id = 0)");
                $update->bind_param("iii", $quantity, $user_id, $product_id);
            }
            $update->execute();
            $update->close();
            $success = "✓ Updated quantity in cart!";
        } else {
            $cart_check->close();
            // Insert into cart
            if ($variation_id && $variation_label) {
                $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, variation_id, variation_name) VALUES (?, ?, ?, ?, ?)");
                $insert->bind_param("iiiis", $user_id, $product_id, $quantity, $variation_id, $variation_label);
            } else {
                $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $insert->bind_param("iii", $user_id, $product_id, $quantity);
            }
            $insert->execute();
            $insert->close();
            $variation_label ? $success = "✓ Added to cart — Size: $variation_label!" : $success = "✓ Added to cart successfully!";
        }
    } else {
        $check->close();
    }
}

// Build query with search and filter
$where = "is_available = 1";
$params = [];
$types = "";

if ($search) {
    $search_escaped = "%" . $conn->real_escape_string($search) . "%";
    $where .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = &$search_escaped;
    $params[] = &$search_escaped;
    $types .= "ss";
}

if ($category) {
    $category_escaped = $conn->real_escape_string($category);
    $where .= " AND category = ?";
    $params[] = &$category_escaped;
    $types .= "s";
}

$products = $conn->prepare("SELECT * FROM products WHERE $where ORDER BY category ASC, name ASC");

if (!empty($params)) {
    $products->bind_param($types, ...$params);
}

$products->execute();
$products_result = $products->get_result();

// Get all categories
$categories = $conn->query("SELECT DISTINCT category FROM products WHERE is_available = 1 ORDER BY category");

// Get cart count
$cart_stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_row = $cart_result->fetch_assoc();
$cart_count = $cart_row['total'] ?? 0;
$cart_stmt->close();

// Get pending reservations count
$pending_stmt = $conn->prepare("SELECT COUNT(*) as total FROM reservations WHERE user_id = ? AND status = 'pending'");
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_row = $pending_result->fetch_assoc();
$pending_count = $pending_row['total'] ?? 0;
$pending_stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Products - E-Reserve for Crochet Flowers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Search and Filter Styles */
        .search-filter-container {
            background: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.6);
        }
        
        .search-form {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .search-input-wrapper {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        
        .search-input-wrapper input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
        }
        
        .search-input-wrapper input:focus {
            outline: none;
            border-color: #f472b6;
            box-shadow: 0 0 0 3px rgba(244, 114, 182, 0.15);
        }
        
        .search-input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 18px;
        }
        
        .filter-select {
            padding: 14px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            background: white;
            cursor: pointer;
            min-width: 180px;
            transition: all 0.2s;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #f472b6;
        }
        
        .btn-search {
            padding: 14px 28px;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3);
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 114, 182, 0.4);
        }
        
        .btn-clear {
            padding: 14px 20px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-clear:hover {
            background: #e2e8f0;
        }
        
        .results-info {
            margin-top: 16px;
            color: #64748b;
            font-size: 14px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 28px;
            margin-top: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12);
            border-color: #f472b6;
        }
        
        .product-image {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #fbcfe8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 90px;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-image .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }
        
        .product-info {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .product-info h3 {
            margin: 0 0 12px 0;
            color: #1e293b;
            font-size: 20px;
            font-weight: 700;
        }
        
        .product-info .description {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 16px;
            line-height: 1.6;
            min-height: 44px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-info .price {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
        }
        
        .product-info .stock {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .product-info .stock.in-stock {
            color: #16a34a;
        }
        
        .product-info .stock.out-of-stock {
            color: #dc2626;
        }
        
        /* Variation Selector */
        .variation-selector {
            margin-bottom: 14px;
        }
        
        .variation-selector label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        
        .variation-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .variation-option {
            display: none; /* hidden radio */
        }
        
        .variation-option-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8fafc;
            min-width: 70px;
            text-align: center;
        }
        
        .variation-option-label:hover {
            border-color: #f472b6;
            background: #fdf2f8;
        }
        
        .variation-option:checked + .variation-option-label {
            border-color: #f472b6;
            background: linear-gradient(135deg, #fdf2f8, #fce7f3);
            box-shadow: 0 0 0 3px rgba(244, 114, 182, 0.2);
        }
        
        .variation-option-label .var-name {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .variation-option-label .var-price {
            font-size: 12px;
            font-weight: 700;
            color: #7c3aed;
            margin-top: 2px;
        }
        
        .add-to-cart-form {
            display: flex;
            gap: 12px;
            align-items: stretch;
            margin-top: auto;
        }

        .product-info > .btn-add {
            margin-top: auto;
        }
        
        .add-to-cart-form input[type="number"] {
            width: 84px;
            min-width: 84px;
            height: 46px;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            text-align: center;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
        .add-to-cart-form input[type="number"]:focus {
            outline: none;
            border-color: #f472b6;
        }
        
        .btn-add {
            flex: 1;
            height: 46px;
            padding: 14px 20px;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            color: white;
            border: none;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3);
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 114, 182, 0.4);
        }
        
        .btn-add:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .cart-badge {
            background: #f472b6;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .page-title {
            margin-bottom: 30px;
        }
        
        .page-title h2 {
            margin: 0 0 8px 0;
            font-size: 28px;
            color: #1e293b;
            font-weight: 700;
        }
        
        .page-title p {
            margin: 0;
            color: #64748b;
        }
        
        /* Image Modal */
        .image-modal { display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); }
        .image-modal.show { display: flex; align-items: center; justify-content: center; }
        .image-modal-content { max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 10px 50px rgba(0,0,0,0.5); }
        .image-modal-close { position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .image-modal-close:hover { color: #f472b6; }
        .product-image { width: 100%; height: 220px; background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #fbcfe8 100%); display: flex; align-items: center; justify-content: center; font-size: 90px; overflow: hidden; cursor: pointer; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .product-image:hover img { transform: scale(1.05); }

        /* Dynamic price display */
        .price-display {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
            transition: all 0.2s;
        }

        .has-variations-hint {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🌸 Crochet</h2>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="products.php" class="active"><i class="far fa-gem"></i> Products</a>
        <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
        <a href="reservations.php"><i class="fas fa-clipboard-list"></i> My Reservations</a>
        <a href="pickup_calendar.php"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">

        <div class="topbar">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</h1>
            <a href="cart.php" style="display: flex; align-items: center; gap: 8px; color: #f472b6; text-decoration: none; font-weight: 600;">
                🛒 Cart <?php if ($cart_count > 0) echo "<span class='cart-badge'>$cart_count</span>"; ?>
            </a>
        </div>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="page-title">
            <h2>🧶 Our Crochet Flower Products</h2>
            <p>Discover our beautiful handmade collection</p>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter-container">
            <form method="GET" class="search-form" id="searchForm">
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="searchInput" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="category" class="filter-select" id="categorySelect">
                    <option value="">All Categories</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn-search">🔍 Search</button>
                <a href="products.php" class="btn-clear">Clear</a>
            </form>
            <?php if ($search || $category): ?>
                <p class="results-info">Showing <?php echo $products_result->num_rows; ?> result(s)<?php echo $search ? " for \"" . htmlspecialchars($search) . "\"" : ''; ?><?php echo $category ? " in \"" . htmlspecialchars($category) . "\"" : ''; ?></p>
            <?php endif; ?>
        </div>

        <div class="products-grid">
            <?php while ($product = $products_result->fetch_assoc()):
                // Fetch variations for this product
                $var_stmt = $conn->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY is_default DESC, price ASC");
                $var_stmt->bind_param("i", $product['id']);
                $var_stmt->execute();
                $var_result = $var_stmt->get_result();
                $variations = [];
                while ($v = $var_result->fetch_assoc()) {
                    $variations[] = $v;
                }
                $var_stmt->close();
                $has_variations = count($variations) > 0;
                
                // Determine display price (default variation or base price)
                $display_price = $product['price'];
                $default_variation_id = null;
                foreach ($variations as $v) {
                    if ($v['is_default']) {
                        $display_price = $v['price'];
                        $default_variation_id = $v['id'];
                        break;
                    }
                }
                if (!$default_variation_id && $has_variations) {
                    $display_price = $variations[0]['price'];
                    $default_variation_id = $variations[0]['id'];
                }
                
                $card_id = 'product_' . $product['id'];
            ?>
                <div class="product-card" id="<?php echo $card_id; ?>">
                    <div class="product-image" onclick="<?php echo !empty($product['image']) ? "openImageModal('../" . htmlspecialchars($product['image']) . "')" : ""; ?>">
                        <?php if (!empty($product['image'])): ?>
                            <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <span class="no-image">🌸</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="description"><?php echo htmlspecialchars($product['description']); ?></p>
                        
                        <?php if ($has_variations): ?>
                            <span class="has-variations-hint">🎨 <?php echo count($variations); ?> size<?php echo count($variations) > 1 ? 's' : ''; ?> available</span>
                        <?php endif; ?>
                        
                        <div class="price-display" id="price_<?php echo $product['id']; ?>">
                            ₱<?php echo number_format($display_price, 2); ?>
                        </div>
                        
                        <div class="stock <?php echo $product['stock'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                            <?php if ($product['stock'] > 0): ?>
                                ✅ In Stock (<?php echo $product['stock']; ?> available)
                            <?php else: ?>
                                ❌ Out of Stock
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($product['stock'] > 0): ?>
                            <form method="POST" class="add-to-cart-form" style="flex-direction: column; gap: 10px;">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="variation_id" id="selected_var_<?php echo $product['id']; ?>" value="<?php echo $default_variation_id ?? ''; ?>">
                                
                                <?php if ($has_variations): ?>
                                    <!-- Variation Selector -->
                                    <div class="variation-selector">
                                        <label>🎨 Choose Size:</label>
                                        <div class="variation-options">
                                            <?php foreach ($variations as $idx => $v): ?>
                                                <input 
                                                    type="radio" 
                                                    class="variation-option" 
                                                    name="var_radio_<?php echo $product['id']; ?>" 
                                                    id="var_<?php echo $v['id']; ?>" 
                                                    value="<?php echo $v['id']; ?>"
                                                    data-price="<?php echo $v['price']; ?>"
                                                    data-product="<?php echo $product['id']; ?>"
                                                    <?php echo ($v['id'] == $default_variation_id) ? 'checked' : ''; ?>
                                                    onchange="selectVariation(<?php echo $product['id']; ?>, <?php echo $v['id']; ?>, <?php echo $v['price']; ?>)"
                                                >
                                                <label class="variation-option-label" for="var_<?php echo $v['id']; ?>">
                                                    <span class="var-name"><?php echo htmlspecialchars($v['variation_label']); ?></span>
                                                    <span class="var-price">₱<?php echo number_format($v['price'], 2); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 12px; align-items: stretch;">
                                    <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" style="width: 84px; min-width: 84px; height: 46px; padding: 12px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 15px; font-weight: 600; text-align: center; box-sizing: border-box;">
                                    <button type="submit" name="add_to_cart" class="btn-add" style="flex: 1; height: 46px;">🛒 Add to Cart</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <button class="btn-add" disabled style="margin-top: auto;">Out of Stock</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if ($products_result->num_rows == 0): ?>
            <div class="empty-state">
                <div class="icon">🧶</div>
                <h2>No Products Available</h2>
                <p>No products available at the moment. Please check back later!</p>
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
// Auto-submit form when category is selected
document.getElementById('categorySelect').addEventListener('change', function() {
    document.getElementById('searchForm').submit();
});

// Live search without page refresh
const productCards = Array.from(document.querySelectorAll('.product-card'));
const liveSearchInput = document.getElementById('searchInput');

function applyLiveProductFilter() {
    const term = liveSearchInput.value.trim().toLowerCase();
    let visibleCount = 0;

    productCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        const match = term === '' || text.includes(term);
        card.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });

    let liveEmptyState = document.getElementById('live-empty-state');
    if (visibleCount === 0 && productCards.length > 0) {
        if (!liveEmptyState) {
            liveEmptyState = document.createElement('div');
            liveEmptyState.id = 'live-empty-state';
            liveEmptyState.className = 'empty-state';
            liveEmptyState.innerHTML = '<div class="icon">🔍</div><h2>No matching products</h2><p>Try a different keyword.</p>';
            document.querySelector('.products-grid').after(liveEmptyState);
        }
    } else if (liveEmptyState) {
        liveEmptyState.remove();
    }
}

liveSearchInput.addEventListener('input', applyLiveProductFilter);

document.getElementById('searchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.keyCode === 13) {
        e.preventDefault();
        applyLiveProductFilter();
    }
});

// Variation selection handler
function selectVariation(productId, variationId, price) {
    // Update hidden input
    document.getElementById('selected_var_' + productId).value = variationId;
    // Update displayed price
    const priceEl = document.getElementById('price_' + productId);
    if (priceEl) {
        priceEl.textContent = '₱' + parseFloat(price).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}

function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').classList.add('show');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('show');
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImageModal();
    }
});
</script>

<!-- Image Preview Modal -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
    <img class="image-modal-content" id="modalImage" src="" alt="Product Image">
</div>

</body>
</html>