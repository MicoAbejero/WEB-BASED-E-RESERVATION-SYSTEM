<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

require_permission('dashboard.customer.view', '../login.php');

// Get user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Get stats
$reservations = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id")->fetch_assoc()['total'];
$pending = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id AND status = 'pending'")->fetch_assoc()['total'];
$completed = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id AND status = 'completed'")->fetch_assoc()['total'];

// Get recent notifications (last 5 reservations with status changes)
$notifications = $conn->query("SELECT r.*, COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name 
                              FROM reservations r 
                              LEFT JOIN products p ON r.product_id = p.id 
                              WHERE r.user_id = $user_id 
                              ORDER BY r.created_at DESC LIMIT 5");

// Get pending reservations count
$pending_count = $pending;

// Get featured products
$featuredProducts = $conn->query("SELECT * FROM products WHERE stock > 0 ORDER BY created_at DESC LIMIT 3");

// Get cart item count
$cart_count = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard - E-Reserve for Crochet Flowers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 10px;
            font-size: 24px;
            color: #64748b;
            transition: color 0.3s;
        }
        
        .notification-bell:hover {
            color: #f472b6;
        }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }
        
        .notification-dropdown {
            display: none;
            position: fixed;
            top: 100px;
            right: 40px;
            width: 380px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            overflow: hidden;
            max-height: 450px;
            overflow-y: auto;
            border: 2px solid #f472b6;
        }
        
        .notification-dropdown.show {
            display: block;
        }
        
        .notification-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-item {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            gap: 12px;
            transition: background 0.2s;
        }
        
        .notification-item:hover {
            background: #f8fafc;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .notification-icon.pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .notification-icon.confirmed {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .notification-icon.completed {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .notification-icon.cancelled {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-content h4 {
            margin: 0 0 4px 0;
            font-size: 14px;
            color: #1e293b;
        }
        
        .notification-content p {
            margin: 0;
            font-size: 12px;
            color: #64748b;
        }
        
        .notification-time {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
        }
        
        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: #94a3b8;
        }
        
        .notification-empty i {
            font-size: 48px;
            margin-bottom: 12px;
            display: block;
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
            max-width: 400px;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast.success {
            border-left: 4px solid #22c55e;
        }
        
        .toast.info {
            border-left: 4px solid #3b82f6;
        }
        
        .toast.warning {
            border-left: 4px solid #f59e0b;
        }
        
        .toast-icon {
            font-size: 24px;
        }
        
        .toast-content h4 {
            margin: 0 0 4px 0;
            font-size: 14px;
            color: #1e293b;
        }
        
        .toast-content p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
        }
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<div class="container">

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🌸 Crochet</h2>
        <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="products.php"><i class="far fa-gem"></i> Products</a>
        <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
        <a href="reservations.php"><i class="fas fa-clipboard-list"></i> My Reservations</a>
        <a href="pickup_calendar.php"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">

        <div class="topbar">
            <h1>Welcome, <?php echo htmlspecialchars($user_name); ?> 👋</h1>
            <div class="topbar-right">
                <!-- Notification Bell -->
                <div class="notification-bell" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($pending_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <span><i class="fas fa-bell"></i> Notifications</span>
                        <a href="reservations.php" style="color: white; text-decoration: none; font-size: 12px;">View All →</a>
                    </div>
                    <?php if ($notifications->num_rows > 0): ?>
                        <?php while ($notif = $notifications->fetch_assoc()): ?>
                            <div class="notification-item">
                                <div class="notification-icon <?php echo $notif['status']; ?>">
                                    <?php 
                                        switch($notif['status']) {
                                            case 'pending': echo '⏳'; break;
                                            case 'confirmed': echo '✅'; break;
                                            case 'completed': echo '🎉'; break;
                                            case 'cancelled': echo '❌'; break;
                                        }
                                    ?>
                                </div>
                                <div class="notification-content">
                                    <h4>Order #<?php echo $notif['id']; ?>: <?php echo ucfirst($notif['status']); ?></h4>
                                    <p><?php echo htmlspecialchars($notif['product_name']); ?> (x<?php echo $notif['quantity']; ?>)</p>
                                    <div class="notification-time"><?php echo date('M d, Y - g:i A', strtotime($notif['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="notification-empty">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notifications yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="hero-section">
            <h1>🌸 Welcome to E-Reserve!</h1>
            <p>Your one-stop shop for beautiful handmade crochet flowers</p>
        </div>

        <!-- Stats Cards -->
        <div class="cards">
            <div class="card stat-total">
                <div class="card-icon">📋</div>
                <h3>Total Reservations</h3>
                <p><?php echo $reservations; ?></p>
            </div>

            <div class="card stat-pending">
                <div class="card-icon">⏳</div>
                <h3>Pending Orders</h3>
                <p><?php echo $pending; ?></p>
            </div>

            <div class="card stat-completed">
                <div class="card-icon">🎉</div>
                <h3>Completed Orders</h3>
                <p><?php echo $completed; ?></p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-header">
            <h2>🚀 Quick Actions</h2>
        </div>
        <div class="quick-actions">
            <a href="products.php" class="action-card">
                <span class="icon">🌺</span>
                <h3>Browse Products</h3>
                <p>View our beautiful crochet flower collection</p>
            </a>

            <a href="cart.php" class="action-card">
                <span class="icon">🛒</span>
                <h3>Shopping Cart</h3>
                <p>View and manage your cart items</p>
            </a>

            <a href="reservations.php" class="action-card">
                <span class="icon">📋</span>
                <h3>My Reservations</h3>
                <p>Track your orders and reservations</p>
            </a>
        </div>

        <!-- Featured Products -->
        <?php if ($featuredProducts->num_rows > 0): ?>
        <div class="section-header" style="margin-top: 50px;">
            <h2>✨ Featured Products</h2>
            <a href="products.php" style="color: #f472b6; text-decoration: none; font-weight: 600;">View All →</a>
        </div>
        <div class="cards">
            <?php while ($product = $featuredProducts->fetch_assoc()): ?>
                <div class="card">
                    <div class="card-icon">🌸</div>
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p style="font-size: 18px; background: linear-gradient(135deg, #f472b6, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">₱<?php echo number_format($product['price'], 2); ?></p>
                    <p style="font-size: 12px; color: #64748b; margin-top: 8px;">Stock: <?php echo $product['stock']; ?></p>
                    <a href="products.php" class="btn-update" style="display: inline-block; margin-top: 12px; text-decoration: none;">View Details</a>
                </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <!-- Tips Section -->
        <div class="feature-card" style="margin-top: 40px; background: linear-gradient(135deg, #fef3c7, #fde68a);">
            <div style="display: flex; align-items: center; gap: 16px;">
                <span style="font-size: 48px;">💡</span>
                <div>
                    <h3 style="margin: 0 0 8px 0; color: #92400e;">Order Tips</h3>
                    <p style="margin: 0; color: #a16207; font-size: 14px; line-height: 1.6;">
                        Browse our collection, add items to your cart, and select your preferred pickup date. 
                        Your order will be confirmed once an admin approves it. Track your reservations anytime!
                    </p>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
// Store last known reservations for change detection
let lastReservationStates = {};

function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
}

// Close notification dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('notificationDropdown');
    const bell = document.querySelector('.notification-bell');
    
    if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Show toast notification
function showToast(type, title, message) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'warning') icon = 'fa-exclamation-circle';
    
    toast.innerHTML = `
        <div class="toast-icon"><i class="fas ${icon}" style="color: ${type === 'success' ? '#22c55e' : type === 'warning' ? '#f59e0b' : '#3b82f6'}"></i></div>
        <div class="toast-content">
            <h4>${title}</h4>
            <p>${message}</p>
        </div>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Poll for reservation status updates
function pollForUpdates() {
    fetch('../api_data.php?action=get_user_reservations')
        .then(response => response.json())
        .then(data => {
            if (data.reservations) {
                data.reservations.forEach(res => {
                    const key = res.id;
                    const currentStatus = res.status;
                    
                    // Check if this is a new reservation
                    if (!lastReservationStates[key]) {
                        lastReservationStates[key] = currentStatus;
                    }
                    
                    // Check if status changed
                    if (lastReservationStates[key] !== currentStatus && lastReservationStates[key] !== undefined) {
                        // Status changed! Show toast notification
                        let title = 'Reservation Updated';
                        let message = `Order #${res.id} is now ${currentStatus}`;
                        
                        if (currentStatus === 'confirmed') {
                            showToast('success', title, message);
                        } else if (currentStatus === 'completed') {
                            showToast('success', '🎉 Order Completed!', message);
                        } else if (currentStatus === 'cancelled') {
                            showToast('warning', '⚠️ Order Cancelled', message);
                        }
                        
                        // Update the badge count
                        updateNotificationBadge();
                    }
                    
                    lastReservationStates[key] = currentStatus;
                });
            }
            
            // Update notification badge
            updateNotificationBadge();
        })
        .catch(err => console.log('Poll error:', err));
}

function updateNotificationBadge() {
    fetch('../api_data.php?action=get_user_reservations')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notification-badge');
            const pending = data.stats ? data.stats.pending : 0;
            
            if (pending > 0) {
                if (badge) {
                    badge.textContent = pending;
                } else {
                    const bell = document.querySelector('.notification-bell');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.textContent = pending;
                    bell.appendChild(newBadge);
                }
            } else if (badge) {
                badge.remove();
            }
        })
        .catch(err => console.log('Badge update error:', err));
}

// Start polling every 5 seconds
setInterval(pollForUpdates, 5000);
</script>

<script src="../assets/js/script.js"></script>
</body>
</html>
