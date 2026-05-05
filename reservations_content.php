<?php
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get reservations with filters
if ($filter_status == 'all') {
    $reservations = $conn->query("SELECT r.*, u.name as customer_name, u.email as customer_email, 
                                 COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name 
                                 FROM reservations r 
                                 JOIN users u ON r.user_id = u.id 
                                 LEFT JOIN products p ON r.product_id = p.id 
                                 ORDER BY r.created_at DESC");
} else {
    $reservations = $conn->query("SELECT r.*, u.name as customer_name, u.email as customer_email, 
                                 COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name 
                                 FROM reservations r 
                                 JOIN users u ON r.user_id = u.id 
                                 LEFT JOIN products p ON r.product_id = p.id 
                                 WHERE r.status = '$filter_status'
                                 ORDER BY r.created_at DESC");
}

// Stats
$total = $conn->query("SELECT COUNT(*) as total FROM reservations")->fetch_assoc()['total'];
$pending = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'")->fetch_assoc()['total'];
$confirmed = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'confirmed'")->fetch_assoc()['total'];
$completed = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'completed'")->fetch_assoc()['total'];
$cancelled = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'cancelled'")->fetch_assoc()['total'];
?>

<!-- Filter Section with proper spacing -->
<div class="filter-section">
    <div class="filter-bar">
        <a href="reservations.php" class="filter-btn <?php echo $filter_status == 'all' ? 'active' : ''; ?>">
            📋 All <span class="count" id="count-all"><?php echo $total; ?></span>
        </a>
        <a href="?status=pending" class="filter-btn <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">
            ⏳ Pending <span class="count" id="count-pending"><?php echo $pending; ?></span>
        </a>
        <a href="?status=confirmed" class="filter-btn <?php echo $filter_status == 'confirmed' ? 'active' : ''; ?>">
            ✅ Confirmed <span class="count" id="count-confirmed"><?php echo $confirmed; ?></span>
        </a>
        <a href="?status=completed" class="filter-btn <?php echo $filter_status == 'completed' ? 'active' : ''; ?>">
            🎉 Completed <span class="count" id="count-completed"><?php echo $completed; ?></span>
        </a>
        <a href="?status=cancelled" class="filter-btn <?php echo $filter_status == 'cancelled' ? 'active' : ''; ?>">
            ❌ Cancelled <span class="count" id="count-cancelled"><?php echo $cancelled; ?></span>
        </a>
    </div>
</div>

<?php if ($reservations->num_rows > 0): ?>
    <div class="reservations-table">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Pickup Date</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Note</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($res = $reservations->fetch_assoc()): ?>
                    <tr data-id="<?php echo $res['id']; ?>">
                        <td>#<?php echo $res['id']; ?></td>
                        <td>
                            <div class="customer-info">
                                <div class="name"><?php echo htmlspecialchars($res['customer_name']); ?></div>
                                <div class="email"><?php echo htmlspecialchars($res['customer_email']); ?></div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($res['product_name']); ?></td>
                        <td><?php echo $res['quantity']; ?></td>
                        <td class="price-col">₱<?php echo number_format($res['total_amount'], 2); ?></td>
                        <td>
                            <?php
                            $pickupDate = $res['pickup_date'] ?? null;
                            if (empty($pickupDate) || $pickupDate === '0000-00-00') {
                                $pickupDate = !empty($res['reservation_date']) ? date('Y-m-d', strtotime($res['reservation_date'] . ' +3 days')) : null;
                            }
                            echo !empty($pickupDate) ? date('M d, Y', strtotime($pickupDate)) : '-';
                            ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $res['status']; ?>">
                                <?php echo ucfirst($res['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="notes-cell" title="<?php echo htmlspecialchars($res['notes'] ?? ''); ?>">
                                <?php echo $res['notes'] ? htmlspecialchars($res['notes']) : '-'; ?>
                            </div>
                        </td>
                        <td>
                            <div class="notes-cell" title="<?php echo htmlspecialchars($res['cancellation_reason'] ?? ''); ?>">
                                <?php echo !empty($res['cancellation_reason']) ? htmlspecialchars($res['cancellation_reason']) : '-'; ?>
                            </div>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                <input type="hidden" name="cancellation_reason" value="">
                                <select name="status" data-current-status="<?php echo htmlspecialchars($res['status']); ?>" onchange="handleStatusChange(this)" style="padding: 6px 10px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                    <option value="pending" <?php echo $res['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $res['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="completed" <?php echo $res['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $res['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="icon">📋</div>
        <h2>No Reservations Found</h2>
        <p>There are no reservations matching this filter.</p>
    </div>
<?php endif; ?>

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
