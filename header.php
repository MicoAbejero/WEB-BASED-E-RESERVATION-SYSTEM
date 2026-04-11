<?php
/**
 * Global Header Component
 * Include this at the top of every page for consistent UI
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Determine base path based on current location
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$is_user = isset($_SESSION['role']) && $_SESSION['role'] === 'customer';

function render_sidebar($current_page, $is_admin, $is_user) {
    if ($is_admin) {
        render_admin_sidebar($current_page);
    } elseif ($is_user) {
        render_user_sidebar($current_page);
    }
}

function render_admin_sidebar($current_page) {
    $nav_items = [
        'dashboard' => ['icon' => '📊', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
        'customers' => ['icon' => '👥', 'label' => 'Customers', 'href' => 'customers.php'],
        'products' => ['icon' => '🧶', 'label' => 'Products', 'href' => 'products.php'],
        'reservations' => ['icon' => '📋', 'label' => 'Reservations', 'href' => 'reservations.php'],
        'pickup_calendar' => ['icon' => '📅', 'label' => 'Pickup Calendar', 'href' => 'pickup_calendar.php'],
        'profile' => ['icon' => '👤', 'label' => 'My Profile', 'href' => 'profile.php'],
    ];
    
    echo '<div class="sidebar">';
    echo '<h2>🌸 Crochet Admin</h2>';
    
    foreach ($nav_items as $key => $item) {
        $active = ($current_page === $key) ? ' class="active"' : '';
        echo '<a href="' . $item['href'] . '"' . $active . '>' . $item['icon'] . ' ' . $item['label'] . '</a>';
    }
    
    echo '<a href="../logout.php" onclick="return confirm(\'Are you sure you want to logout?\')">🚪 Logout</a>';
    echo '</div>';
}

function render_user_sidebar($current_page) {
    $nav_items = [
        'dashboard' => ['icon' => '🏠', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
        'products' => ['icon' => '🧶', 'label' => 'Products', 'href' => 'products.php'],
        'cart' => ['icon' => '🛒', 'label' => 'Cart', 'href' => 'cart.php'],
        'reservations' => ['icon' => '📋', 'label' => 'My Reservations', 'href' => 'reservations.php'],
        'pickup_calendar' => ['icon' => '📅', 'label' => 'Pickup Calendar', 'href' => 'pickup_calendar.php'],
        'profile' => ['icon' => '👤', 'label' => 'My Profile', 'href' => 'profile.php'],
    ];
    
    echo '<div class="sidebar">';
    echo '<h2>🌸 Crochet</h2>';
    
    foreach ($nav_items as $key => $item) {
        $active = ($current_page === $key) ? ' class="active"' : '';
        echo '<a href="' . $item['href'] . '"' . $active . '>' . $item['icon'] . ' ' . $item['label'] . '</a>';
    }
    
    echo '<a href="../logout.php" onclick="return confirm(\'Are you sure you want to logout?\')">🚪 Logout</a>';
    echo '</div>';
}

function render_topbar($title = '', $show_notifications = false) {
    global $conn, $is_admin, $is_user;
    
    $user_name = $_SESSION['name'] ?? 'User';
    $cart_count = 0;
    $pending_count = 0;
    
    if (isset($_SESSION['user_id']) && $conn) {
        $user_id = $_SESSION['user_id'];
        
        if ($is_user) {
            $cart_result = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id");
            $cart_count = $cart_result->fetch_assoc()['total'] ?? 0;
            
            $pending_result = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id AND status = 'pending'");
            $pending_count = $pending_result->fetch_assoc()['total'] ?? 0;
        }
        
        if ($is_admin) {
            $pending_result = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'");
            $pending_count = $pending_result->fetch_assoc()['total'] ?? 0;
        }
    }
    
    echo '<div class="topbar">';
    
    if ($title) {
        echo '<h1>' . htmlspecialchars($title) . '</h1>';
    } else {
        echo '<h1>Welcome, ' . htmlspecialchars($user_name) . ' 👋</h1>';
    }
    
    echo '<div class="topbar-right">';
    
    // Cart badge for users
    if ($is_user && $cart_count > 0) {
        echo '<a href="cart.php" class="cart-icon" title="Shopping Cart">';
        echo '<span>🛒</span>';
        echo '<span class="badge">' . $cart_count . '</span>';
        echo '</a>';
    }
    
    // Notification bell
    if ($show_notifications || $pending_count > 0) {
        echo '<div class="notification-bell" onclick="toggleNotifications()">';
        echo '<i class="fas fa-bell"></i>';
        if ($pending_count > 0) {
            echo '<span class="notification-badge">' . $pending_count . '</span>';
        }
        echo '</div>';
    }
    
    // Live indicator
    echo '<div class="live-indicator">';
    echo '<span class="dot"></span>';
    echo 'Live';
    echo '</div>';
    
    echo '</div>'; // topbar-right
    echo '</div>'; // topbar
}
?>
