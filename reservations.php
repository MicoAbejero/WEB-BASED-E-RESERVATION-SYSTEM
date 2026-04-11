<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

require_permission('reservation.view_own', '../login.php');

$user_id = $_SESSION['user_id'];
$message = "";

// Auto-fix: Check and add missing columns safely
$columns_to_add = [
    'last_updated_by' => 'INT DEFAULT NULL',
    'last_updated_by_role' => "ENUM(\"admin\", \"customer\") DEFAULT NULL",
    'last_updated_at' => 'TIMESTAMP NULL DEFAULT NULL',
    'pickup_date' => 'DATE DEFAULT NULL'
];

foreach ($columns_to_add as $col_name => $col_def) {
    $check = $conn->query("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = 'e_reserve_db' AND TABLE_NAME = 'reservations' 
                           AND COLUMN_NAME = '$col_name'")->fetch_assoc();
    if ($check['cnt'] == 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN $col_name $col_def");
    }
}

// Handle cancel reservation
if (isset($_POST['cancel_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    
    // Get reservation details first (only allow cancellation for pending orders)
    $res = $conn->query("SELECT * FROM reservations WHERE id = $reservation_id AND user_id = $user_id AND status = 'pending'");
    
    if ($res->num_rows > 0) {
        $reservation = $res->fetch_assoc();
        
        // Restore stock
        $conn->query("UPDATE products SET stock = stock + {$reservation['quantity']} WHERE id = {$reservation['product_id']}");
        
        // Update status with tracking
        $conn->query("UPDATE reservations SET status = 'cancelled', last_updated_by=$user_id, last_updated_by_role='customer', last_updated_at=NOW() WHERE id = $reservation_id");
        
        $message = "✓ Reservation cancelled successfully!";
    }
}

// Get reservations
$reservations = $conn->query("SELECT r.*, 
                             COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name,
                             COALESCE(p.image, r.product_image_snapshot) as image
                             FROM reservations r 
                             LEFT JOIN products p ON r.product_id = p.id 
                             WHERE r.user_id = $user_id 
                             ORDER BY r.created_at DESC");

// Stats
$total_reservations = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id")->fetch_assoc()['total'];
$pending = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id AND status = 'pending'")->fetch_assoc()['total'];
$completed = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id AND status = 'completed'")->fetch_assoc()['total'];
$cancelled = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id AND status = 'cancelled'")->fetch_assoc()['total'];

// Get cart item count
$cart_count = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Reservations - E-Reserve for Crochet Flowers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .reservations-list {
            background: white;
            border-radius: 20px;
            padding: 28px;
            margin-top: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(226, 232, 240, 0.6);
        }
        
        .reservations-list h3 {
            margin: 0 0 24px 0;
            color: #1e293b;
            font-size: 20px;
            font-weight: 700;
        }
        
        .reservation-item {
            display: flex;
            gap: 24px;
            padding: 24px;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s ease;
            border-radius: 12px;
            margin-bottom: 12px;
        }
        
        .reservation-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .reservation-item:hover {
            background: linear-gradient(90deg, #fdf2f8 0%, #faf5ff 100%);
        }
        
        .res-image {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #fbcfe8 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            flex-shrink: 0;
        }
        
        .res-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .res-details h4 {
            margin: 0;
            color: #1e293b;
            font-size: 20px;
            font-weight: 700;
        }
        
        .res-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .meta-item {
            font-size: 14px;
        }
        
        .meta-item .label {
            color: #64748b;
        }
        
        .meta-item .value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .res-total {
            font-size: 22px;
            font-weight: 800;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .res-actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }
        
        .btn-cancel {
            padding: 10px 20px;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: linear-gradient(135deg, #fecaca, #fca5a5);
            transform: translateY(-2px);
        }
        
        .btn-cancel:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }
        
        .btn-browse {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            margin-top: 24px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3);
        }
        
        .btn-browse:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 114, 182, 0.4);
        }
        
        .notes-text {
            color: #64748b;
            font-size: 14px;
            font-style: italic;
            margin-top: 8px;
        }
        
        /* Image Modal */
        .image-modal { display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); }
        .image-modal.show { display: flex; align-items: center; justify-content: center; }
        .image-modal-content { max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 10px 50px rgba(0,0,0,0.5); }
        .image-modal-close { position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .image-modal-close:hover { color: #f472b6; }
        .res-image:hover img { transform: scale(1.05); }
    </style>
</head>
<body>

<div class="container">

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🌸 Crochet</h2>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="products.php"><i class="far fa-gem"></i> Products</a>
        <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
        <a href="reservations.php" class="active"><i class="fas fa-clipboard-list"></i> My Reservations</a>
        <a href="pickup_calendar.php"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">

        <div class="topbar">
            <h1>📋 My Reservations</h1>
        </div>

        <?php if ($message): ?>
            <div class="success-message" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; padding: 18px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2);">
                <span style="font-size: 24px;">🎉</span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2>Track Your Orders</h2>
            <p>View and manage your reservations</p>
        </div>

        <div class="cards">
            <div class="card stat-total">
                <div class="card-icon">📋</div>
                <h3>Total Reservations</h3>
                <p id="stat-total"><?php echo $total_reservations; ?></p>
            </div>
            <div class="card stat-pending">
                <div class="card-icon">⏳</div>
                <h3>Pending</h3>
                <p id="stat-pending"><?php echo $pending; ?></p>
            </div>
            <div class="card stat-completed">
                <div class="card-icon">🎉</div>
                <h3>Completed</h3>
                <p id="stat-completed"><?php echo $completed; ?></p>
            </div>
            <div class="card" style="background: linear-gradient(135deg, #fee2e2, #fecaca);">
                <div class="card-icon">❌</div>
                <h3>Cancelled</h3>
                <p><?php echo $cancelled; ?></p>
            </div>
        </div>

        <?php if ($reservations->num_rows > 0): ?>
            <div class="reservations-list">
                <h3>📋 Reservation History</h3>
                
                <?php while ($res = $reservations->fetch_assoc()): ?>
                    <div class="reservation-item">
                        <div class="res-image" style="cursor: pointer;" <?php if (!empty($res['image'])): ?>onclick="openImageModal('../<?php echo htmlspecialchars($res['image']); ?>')"<?php endif; ?>>
                            <?php if (!empty($res['image'])): ?>
                                <img src="../<?php echo htmlspecialchars($res['image']); ?>" alt="<?php echo htmlspecialchars($res['product_name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 16px; transition: transform 0.3s ease;">
                            <?php else: ?>
                                🌸
                            <?php endif; ?>
                        </div>
                        <div class="res-details">
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                <h4><?php echo htmlspecialchars($res['product_name']); ?></h4>
                                <span class="status-badge status-<?php echo $res['status']; ?>">
                                    <?php echo ucfirst($res['status']); ?>
                                </span>
                            </div>
                            
                            <div class="res-meta">
                                <div class="meta-item">
                                    <span class="label">Quantity:</span>
                                    <span class="value"><?php echo $res['quantity']; ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="label">Reserved:</span>
                                    <span class="value"><?php echo date('M d, Y', strtotime($res['reservation_date'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="label">Pickup Date:</span>
                                    <span class="value">
                                        <?php
                                        $pickupDate = $res['pickup_date'] ?? null;
                                        if (empty($pickupDate) || $pickupDate === '0000-00-00') {
                                            $pickupDate = !empty($res['reservation_date']) ? date('Y-m-d', strtotime($res['reservation_date'] . ' +3 days')) : null;
                                        }
                                        echo !empty($pickupDate) ? date('M d, Y', strtotime($pickupDate)) : 'N/A';
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="res-total">Total: ₱<?php echo number_format($res['total_amount'], 2); ?></div>
                            
                            <?php if ($res['notes']): ?>
                                <p class="notes-text">
                                    <strong>Notes:</strong> <?php echo htmlspecialchars($res['notes']);?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($res['last_updated_by_role'])): ?>
                                <p style="font-size: 12px; color: #64748b; margin: 8px 0 0 0;">
                                    <?php if ($res['last_updated_by_role'] === 'admin'): ?>
                                        👑 <strong style="color: #6366f1;">Admin</strong> updated this order
                                    <?php else: ?>
                                        👤 <strong style="color: #f472b6;">You</strong> updated this order
                                    <?php endif; ?>
                                    <span style="color: #94a3b8;">
                                        - <?php echo !empty($res['last_updated_at']) ? date('M d, Y h:i A', strtotime($res['last_updated_at'])) : ''; ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($res['status'] === 'pending'): ?>
                                <div class="res-actions">
                                    <button class="btn-cancel" onclick="cancelReservation(<?php echo $res['id']; ?>)">Cancel Reservation</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">📋</div>
                <h2>No Reservations Yet</h2>
                <p>You haven't made any reservations yet. Start shopping to create your first reservation!</p>
                <a href="products.php" class="btn-browse">🛍️ Browse Products</a>
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
function cancelReservation(reservationId) {
    if (!confirm('⚠️ Are you sure you want to CANCEL this reservation?\n\nThis will restore the product to stock.\n\nThis action cannot be undone!\n\nClick OK to confirm cancellation.')) return;
    
    const formData = new FormData();
    formData.append('action', 'cancel_reservation');
    formData.append('reservation_id', reservationId);
    
    fetch('../api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

// Poll for status updates every 5 seconds
function pollUserData() {
    fetch('../api_data.php?action=get_user_reservations')
        .then(response => response.json())
        .then(data => {
            if (data.stats) {
                document.getElementById('stat-total').textContent = data.stats.total;
                document.getElementById('stat-pending').textContent = data.stats.pending;
                document.getElementById('stat-completed').textContent = data.stats.completed;
            }
            
            // Check for status changes
            if (data.reservations) {
                data.reservations.forEach(res => {
                    const badge = document.querySelector(`.status-badge[data-id="${res.id}"]`);
                    if (badge && badge.textContent.toLowerCase() !== res.status) {
                        // Status changed - reload page
                        location.reload();
                    }
                });
            }
        })
        .catch(err => console.log('Poll error:', err));
}

// Start polling every 5 seconds
setInterval(pollUserData, 5000);

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
