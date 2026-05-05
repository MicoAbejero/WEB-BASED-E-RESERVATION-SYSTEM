<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

require_permission('dashboard.admin.view', '../login.php');

// Get stats
$userCount = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$productCount = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];
$resCount = $conn->query("SELECT COUNT(*) as total FROM reservations")->fetch_assoc()['total'];
$pendingCount = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'")->fetch_assoc()['total'];

// Get recent reservations
$recentReservations = $conn->query("SELECT r.*, u.name as customer_name, COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name 
                                   FROM reservations r 
                                   JOIN users u ON r.user_id = u.id 
                                   LEFT JOIN products p ON r.product_id = p.id 
                                   ORDER BY r.created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - E-Reserve Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .recent-activity {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.6);
        }
        
        .recent-activity h3 {
            margin: 0 0 20px 0;
            color: #1e293b;
            font-size: 18px;
            font-weight: 700;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 10px;
            background: #f8fafc;
            transition: all 0.2s ease;
        }
        
        .activity-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
        }
        
        .activity-item .icon {
            font-size: 24px;
        }
        
        .activity-item .info {
            flex: 1;
        }
        
        .activity-item .name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        
        .activity-item .detail {
            color: #64748b;
            font-size: 12px;
        }
        
        .activity-item .time {
            color: #94a3b8;
            font-size: 11px;
        }
        
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
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
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
            border: 2px solid #ef4444;
        }
        
        .notification-dropdown.show {
            display: block;
        }
        
        .notification-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
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
        
        .notification-icon.new {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #d97706;
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
        
        .new-order-alert {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        .new-order-alert i {
            font-size: 24px;
            color: #d97706;
        }
        
        .new-order-alert h4 {
            margin: 0;
            color: #92400e;
            font-size: 14px;
        }
        
        .new-order-alert p {
            margin: 4px 0 0 0;
            color: #a16207;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<div class="container">

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🌸 Crochet Admin</h2>
        <a href="dashboard.php" class="active"><i class="far fa-chart-bar"></i> Dashboard</a>
        <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
        <a href="products.php"><i class="far fa-gem"></i> Products</a>
        <a href="reservations.php"><i class="fas fa-clipboard-list"></i> Reservations</a>
        <a href="pickup_calendar.php"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">

        <div class="topbar">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</h1>
            <div class="topbar-right">
                <div class="live-indicator">
                    <span class="dot"></span>
                    Live
                </div>
                
                <!-- Notification Bell -->
                <div class="notification-bell" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($pendingCount > 0): ?>
                        <span class="notification-badge"><?php echo $pendingCount; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <span><i class="fas fa-bell"></i> Pending Orders</span>
                        <a href="reservations.php?status=pending" style="color: white; text-decoration: none; font-size: 12px;">View All →</a>
                    </div>
                    <?php 
                    $pendingReservations = $conn->query("SELECT r.*, u.name as customer_name, COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name
                                                      FROM reservations r 
                                                      JOIN users u ON r.user_id = u.id 
                                                      LEFT JOIN products p ON r.product_id = p.id
                                                      WHERE r.status = 'pending'
                                                      ORDER BY r.created_at DESC LIMIT 5");
                    ?>
                    <?php if ($pendingReservations->num_rows > 0): ?>
                        <?php while ($notif = $pendingReservations->fetch_assoc()): ?>
                            <div class="notification-item">
                                <div class="notification-icon new">
                                    ⏳
                                </div>
                                <div class="notification-content">
                                    <h4><?php echo htmlspecialchars($notif['customer_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($notif['product_name']); ?> (x<?php echo $notif['quantity']; ?>)</p>
                                    <div class="notification-time">
                                        <?php echo date('M d, Y - g:i A', strtotime($notif['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="notification-empty">
                            <i class="fas fa-check-circle"></i>
                            <p>All caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="page-header">
            <h2>📊 Dashboard Overview</h2>
            <p>Monitor your crochet business at a glance</p>
        </div>

        <!-- New Order Alert (will be shown dynamically) -->
        <div id="newOrderAlert" style="display: none;">
            <div class="new-order-alert">
                <i class="fas fa-bell"></i>
                <div>
                    <h4>🔔 New Order Received!</h4>
                    <p id="newOrderMessage">A customer just placed a new reservation.</p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="cards">
            <div class="card stat-total">
                <div class="card-icon">👥</div>
                <h3>Total Users</h3>
                <p id="stat-users"><?php echo $userCount; ?></p>
            </div>

            <div class="card stat-confirmed">
                <div class="card-icon">🧶</div>
                <h3>Total Products</h3>
                <p id="stat-products"><?php echo $productCount; ?></p>
            </div>

            <div class="card stat-pending">
                <div class="card-icon">📋</div>
                <h3>Total Reservations</h3>
                <p id="stat-reservations"><?php echo $resCount; ?></p>
            </div>
            
            <?php if ($pendingCount > 0): ?>
            <div class="card stat-cancelled">
                <div class="card-icon">⏳</div>
                <h3>Pending Orders</h3>
                <p id="stat-pending"><?php echo $pendingCount; ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Quick Actions -->
            <div class="feature-card">
                <h3 style="margin: 0 0 20px 0; color: #1e293b; font-size: 18px; font-weight: 700;">🚀 Quick Actions</h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <a href="products.php" class="action-card" style="padding: 20px;">
                        <span class="icon" style="font-size: 36px;">🧶</span>
                        <h3 style="font-size: 14px;">Manage Products</h3>
                        <p style="font-size: 12px;">Add, edit or remove products</p>
                    </a>
                    <a href="reservations.php" class="action-card" style="padding: 20px;">
                        <span class="icon" style="font-size: 36px;">📋</span>
                        <h3 style="font-size: 14px;">View Reservations</h3>
                        <p style="font-size: 12px;">Process customer orders</p>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <h3>📈 Recent Activity</h3>
                <?php if ($recentReservations->num_rows > 0): ?>
                    <?php while ($res = $recentReservations->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="icon">
                                <?php 
                                    switch($res['status']) {
                                        case 'pending': echo '⏳';
                                        case 'confirmed': echo '✅';
                                        case 'completed': echo '🎉';
                                        case 'cancelled': echo '❌';
                                        default: echo '📋';
                                    }
                                ?>
                            </div>
                            <div class="info">
                                <div class="name"><?php echo htmlspecialchars($res['customer_name']); ?></div>
                                <div class="detail"><?php echo htmlspecialchars($res['product_name']); ?> (x<?php echo $res['quantity']; ?>)</div>
                            </div>
                            <div class="time"><?php echo date('M d', strtotime($res['created_at'])); ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No recent activity</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<script>
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
</script>

<script src="../assets/js/script.js"></script>
</body>
</html>
