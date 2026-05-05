<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

require_permission('reservation.manage', '../login.php');

$success = "";

// Auto-fix: Check and add missing columns safely
$columns_to_add = [
    'last_updated_by' => 'INT DEFAULT NULL',
    'last_updated_by_role' => "ENUM(\"admin\", \"customer\") DEFAULT NULL",
    'last_updated_at' => 'TIMESTAMP NULL DEFAULT NULL',
    'pickup_date' => 'DATE DEFAULT NULL',
    'cancellation_reason' => 'TEXT NULL'
];

foreach ($columns_to_add as $col_name => $col_def) {
    $check = $conn->query("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = 'e_reserve_db' AND TABLE_NAME = 'reservations' 
                           AND COLUMN_NAME = '$col_name'")->fetch_assoc();
    if ($check['cnt'] == 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN $col_name $col_def");
    }
}

// Handle status update
if (isset($_POST['update_status'])) {
    $id = (int)$_POST['reservation_id'];
    $status = $_POST['status'];
    $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');
    $reservation = $conn->query("SELECT * FROM reservations WHERE id=$id")->fetch_assoc();

    if (!in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
        $success = "Invalid reservation status.";
    } elseif ($status === 'cancelled' && $cancellation_reason === '') {
        $success = "Cancellation reason is required when cancelling a reservation.";
    } elseif ($reservation) {
        if ($status === 'cancelled' && in_array($reservation['status'], ['pending', 'confirmed'])) {
            $product_check = $conn->query("SELECT id FROM products WHERE id = {$reservation['product_id']}");
            if ($product_check->num_rows > 0) {
                $conn->query("UPDATE products SET stock = stock + {$reservation['quantity']} WHERE id = {$reservation['product_id']}");
            }
        }

        $reason_sql = '';
        if ($status === 'cancelled') {
            $reason = $conn->real_escape_string($cancellation_reason);
            $reason_sql = ", cancellation_reason='$reason'";
        }

        $conn->query("UPDATE reservations SET status='$status', last_updated_by=" . (int)$_SESSION['user_id'] . ", last_updated_by_role='admin', last_updated_at=NOW()$reason_sql WHERE id=$id");
        $success = "✓ Reservation status updated successfully!";
    } else {
        $success = "Reservation not found.";
    }
}

// Handle delete
if (isset($_POST['delete_reservation'])) {
    $id = $_POST['reservation_id'];
    $conn->query("DELETE FROM reservations WHERE id=$id");
    $success = "✓ Reservation deleted successfully!";
}

// Get reservations with customer info
$reservations = $conn->query("
    SELECT r.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
           COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name
    FROM reservations r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN products p ON r.product_id = p.id
    ORDER BY r.created_at DESC
");

// Stats
$total_reservations = $conn->query("SELECT COUNT(*) as total FROM reservations")->fetch_assoc()['total'];
$pending = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status='pending'")->fetch_assoc()['total'];
$confirmed = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status='confirmed'")->fetch_assoc()['total'];
$completed = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status='completed'")->fetch_assoc()['total'];
$cancelled = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status='cancelled'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reservations Management - E-Reserve Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%); min-height: 100vh; }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; height: 100vh; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); color: white; position: fixed; padding-top: 20px; box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15); }
        .sidebar h2 { text-align: center; margin-bottom: 30px; font-size: 24px; }
        .sidebar a { display: flex; align-items: center; gap: 12px; padding: 14px 24px; color: #94a3b8; text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent; font-size: 15px; }
        .sidebar a:hover { background: linear-gradient(90deg, #334155 0%, transparent 100%); color: #ffffff; border-left: 4px solid #f472b6; padding-left: 28px; }
        .sidebar a.active { background: linear-gradient(90deg, #334155 0%, transparent 100%); border-left: 4px solid #f472b6; color: white; }
        
        /* Main */
        .main { margin-left: 260px; padding: 30px 40px; width: 100%; }
        
        .topbar { background: white; padding: 20px 28px; margin-bottom: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); display: flex; align-items: center; justify-content: space-between; }
        .topbar h1 { margin: 0; font-size: 24px; font-weight: 600; color: #1e293b; }
        
        .page-header { margin-bottom: 30px; }
        .page-header h2 { margin: 0 0 10px 0; font-size: 28px; color: #1e293b; font-weight: 700; }
        .page-header p { margin: 0; color: #64748b; font-size: 15px; }
        
        .success-message { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; padding: 18px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2); }
        
        /* Cards */
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .card { background: white; padding: 28px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid rgba(226, 232, 240, 0.6); }
        .card .card-icon { font-size: 40px; margin-bottom: 16px; }
        .card h3 { margin: 0; font-size: 14px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .card p { font-size: 32px; font-weight: 800; margin: 12px 0 0 0; color: #1e293b; }
        
        /* Table */
        .reservations-table { width: 100%; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid rgba(226, 232, 240, 0.6); margin-top: 24px; }
        .reservations-table table { width: 100%; border-collapse: collapse; }
        .reservations-table th { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 18px 16px; text-align: left; color: #475569; font-weight: 700; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        .reservations-table td { padding: 18px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        .reservations-table tbody tr:hover { background: linear-gradient(90deg, #fdf2f8 0%, #faf5ff 100%); }
        
        .customer-info { line-height: 1.6; }
        .customer-info .name { font-weight: 700; color: #1e293b; font-size: 14px; }
        .customer-info .email { color: #64748b; font-size: 12px; }
        
        .status-badge { display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #b45309; }
        .status-confirmed { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1d4ed8; }
        .status-completed { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; }
        .status-cancelled { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; }
        
        .action-form { display: flex; gap: 8px; align-items: center; }
        .action-form select { padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s ease; }
        .action-form select:focus { outline: none; border-color: #f472b6; box-shadow: 0 0 0 3px rgba(244, 114, 182, 0.2); }
        
        .btn-update { padding: 8px 16px; background: linear-gradient(135deg, #f472b6, #c084fc); color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.3s ease; }
        .btn-update:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(244, 114, 182, 0.4); }
        
        .btn-delete { padding: 8px 16px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.3s ease; }
        .btn-delete:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4); }
        
        .price-col { font-weight: 700; color: #7c3aed; font-size: 15px; }
        .notes-cell { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #64748b; font-size: 13px; }
        .cancel-reason { max-width: 220px; color: #991b1b; font-size: 12px; line-height: 1.4; }
        .cancel-reason strong { display: block; color: #dc2626; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 3px; }
    </style>
</head>
<body>

<div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🌸 Crochet Admin</h2>
        <a href="dashboard.php"><i class="far fa-chart-bar"></i> Dashboard</a>
        <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
        <a href="products.php"><i class="far fa-gem"></i> Products</a>
        <a href="reservations.php" class="active"><i class="fas fa-clipboard-list"></i> Reservations</a>
        <a href="pickup_calendar.php"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">
        <div class="topbar">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</h1>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #22c55e; font-weight: 500;">
                <span style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%; animation: pulse 2s infinite;"></span>
                Live
            </div>
        </div>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="page-header">
            <h2>📋 Reservations Management</h2>
            <p>View and manage customer reservations</p>
        </div>

        <div class="cards">
            <div class="card">
                <div class="card-icon">📋</div>
                <h3>Total Reservations</h3>
                <p><?php echo $total_reservations; ?></p>
            </div>
            <div class="card">
                <div class="card-icon">⏳</div>
                <h3>Pending</h3>
                <p><?php echo $pending; ?></p>
            </div>
            <div class="card">
                <div class="card-icon">✅</div>
                <h3>Confirmed</h3>
                <p><?php echo $confirmed; ?></p>
            </div>
            <div class="card">
                <div class="card-icon">🎉</div>
                <h3>Completed</h3>
                <p><?php echo $completed; ?></p>
            </div>
            <div class="card">
                <div class="card-icon">❌</div>
                <h3>Cancelled</h3>
                <p><?php echo $cancelled; ?></p>
            </div>
        </div>

        <div class="reservations-table">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Pickup Date</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th>Last Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $reservations->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="customer-info">
                                    <div class="name"><?php echo htmlspecialchars($row['customer_name'] ?? 'Unknown'); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($row['customer_email'] ?? ''); ?></div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['product_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo (int)$row['quantity']; ?></td>
                            <td class="price-col">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                            <td>
                                <?php
                                $pickupDate = $row['pickup_date'] ?? null;
                                if (empty($pickupDate) || $pickupDate === '0000-00-00') {
                                    $pickupDate = !empty($row['reservation_date']) ? date('Y-m-d', strtotime($row['reservation_date'] . ' +3 days')) : null;
                                }

                                if (!empty($pickupDate)) {
                                    echo date('M d, Y', strtotime($pickupDate));
                                } else {
                                    echo '<span style="color: #94a3b8;">N/A</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'cancelled' && !empty($row['cancellation_reason'])): ?>
                                    <div class="cancel-reason" title="<?php echo htmlspecialchars($row['cancellation_reason']); ?>">
                                        <?php echo htmlspecialchars($row['cancellation_reason']); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 12px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['last_updated_by_role'])): ?>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?php if ($row['last_updated_by_role'] === 'admin'): ?>
                                            <span style="color: #6366f1; font-weight: 600;">👑 Admin</span> updated
                                        <?php else: ?>
                                            <span style="color: #f472b6; font-weight: 600;">👤 Customer</span> updated
                                        <?php endif; ?>
                                        <div style="font-size: 11px; color: #94a3b8;">
                                            <?php echo !empty($row['last_updated_at']) ? date('M d, Y h:i A', strtotime($row['last_updated_at'])) : ''; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 12px;">Not updated yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="cancellation_reason" value="">
                                    <select name="status" data-current-status="<?php echo htmlspecialchars($row['status']); ?>" onchange="handleStatusChange(this)" style="padding: 6px 10px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; <?php echo $row['status'] == 'cancelled' ? 'opacity: 0.5;' : ''; ?>" <?php echo $row['status'] == 'cancelled' ? 'disabled' : ''; ?>>
                                        <option value="pending" <?php echo $row['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $row['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="completed" <?php echo $row['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $row['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function handleStatusChange(select) {
    const form = select.form;
    const previousStatus = select.dataset.currentStatus;
    const reasonInput = form.querySelector('input[name="cancellation_reason"]');

    if (select.value === 'cancelled') {
        const reason = prompt('Enter the reason for cancelling this reservation:');
        if (!reason || !reason.trim()) {
            alert('Cancellation reason is required.');
            select.value = previousStatus;
            return;
        }

        reasonInput.value = reason.trim();
    } else {
        reasonInput.value = '';
    }

    form.submit();
}
</script>

<script src="../assets/js/script.js"></script>
</body>
</html>
