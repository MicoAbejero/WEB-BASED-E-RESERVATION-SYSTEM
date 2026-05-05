<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

require_permission('cart.manage_own', '../login.php');

$user_id = (int)$_SESSION['user_id'];
$message = "";
$success = "";

if (isset($_POST['update_cart'])) {
    $cart_id = (int)$_POST['cart_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity > 0) {
        // Use prepared statement
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = "Cart updated successfully!";
    } else {
        // Use prepared statement
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = "Item removed from cart!";
    }
}

if (isset($_POST['remove_item'])) {
    $cart_id = (int)$_POST['cart_id'];
    
    // Use prepared statement
    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $stmt->close();
    $success = "Item removed from cart!";
}

if (isset($_POST['remove_all'])) {
    // Use prepared statement
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $success = "All items removed from cart!";
}

if (isset($_POST['checkout'])) {
    $pickup_date = $_POST['pickup_date'] ?? '';
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    $selected_cart_ids = isset($_POST['selected_cart_ids']) && is_array($_POST['selected_cart_ids'])
        ? array_values(array_unique(array_filter(array_map('intval', $_POST['selected_cart_ids']))))
        : [];
    
    // Default to 3 days from now if empty
    if (empty($pickup_date)) {
        $pickup_date = date('Y-m-d', strtotime('+3 days'));
    }
    
    // Validate and normalize pickup date format
    $pickup_date = date('Y-m-d', strtotime($pickup_date));
    if ($pickup_date === '1970-01-01' || $pickup_date === '') {
        $pickup_date = date('Y-m-d', strtotime('+3 days'));
    }
    
    // Validate pickup date is in the future
    if (empty($selected_cart_ids)) {
        $message = "Please select at least one cart item to reserve.";
    } elseif (strtotime($pickup_date) < strtotime(date('Y-m-d'))) {
        $message = "Pickup date must be today or in the future.";
    } else {
        // Escape the pickup date for safety
        $pickup_date_escaped = $conn->real_escape_string($pickup_date);
        $notes_escaped = $conn->real_escape_string($notes);
        
        // Get default max using prepared statement
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'max_reservations_per_day'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $default_max = $row ? (int)$row['setting_value'] : 5;
        $stmt->close();
        
        // Get date-specific max using prepared statement
        $date_stmt = $conn->prepare("SELECT max_reservations FROM date_settings WHERE pickup_date = ?");
        $date_stmt->bind_param("s", $pickup_date_escaped);
        $date_stmt->execute();
        $date_result = $date_stmt->get_result();
        $date_row = $date_result->fetch_assoc();
        $max_per_day = $date_row ? (int)$date_row['max_reservations'] : $default_max;
        $date_stmt->close();
        
        // Get current reservations count using prepared statement
        $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM reservations WHERE COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY)) = ? AND status IN ('pending', 'confirmed')");
        $count_stmt->bind_param("s", $pickup_date_escaped);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $current_count = $count_row['total'] ?? 0;
        $count_stmt->close();
        
        $selected_ids_sql = implode(',', $selected_cart_ids);

        // Get selected cart count using prepared statement
        $cart_stmt = $conn->prepare("SELECT COUNT(*) as total FROM cart WHERE user_id = ? AND id IN ($selected_ids_sql)");
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        $cart_row = $cart_result->fetch_assoc();
        $cart_count_val = $cart_row['total'] ?? 0;
        $cart_stmt->close();
        
        if ($cart_count_val < 1) {
            $message = "Please select at least one valid cart item to reserve.";
        } elseif ($current_count + $cart_count_val > $max_per_day) {
            $available = $max_per_day - $current_count;
            if ($available <= 0) {
                $message = "Sorry! This pickup date is fully booked. Please select a different date.";
            } else {
                $message = "Sorry! Only $available slot(s) available for this date. Please select a different date.";
            }
        } else {
            // Get cart items including variation prices
            $items_stmt = $conn->prepare("SELECT c.*, p.name, p.price as base_price, p.image,
                COALESCE(pv.price, p.price) as unit_price,
                COALESCE(pv.variation_name, '') as variation_name,
                COALESCE(pv.variation_label, '') as variation_label
            FROM cart c 
            JOIN products p ON c.product_id = p.id
            LEFT JOIN product_variations pv ON c.variation_id = pv.id
            WHERE c.user_id = ? AND c.id IN ($selected_ids_sql)");
            $items_stmt->bind_param("i", $user_id);
            $items_stmt->execute();
            $cart_items = $items_stmt->get_result();
            
            if ($cart_items->num_rows > 0) {
                $conn->begin_transaction();
                try {
                    while ($item = $cart_items->fetch_assoc()) {
                        $unit_price = isset($item['unit_price']) ? $item['unit_price'] : $item['base_price'];
                        $subtotal = $unit_price * $item['quantity'];
                        $product_id = (int)$item['product_id'];
                        $quantity = (int)$item['quantity'];
                        $variation_name = isset($item['variation_name']) ? $item['variation_name'] : null;
                        $variation_label = isset($item['variation_label']) ? $item['variation_label'] : null;
                        
                        // Insert reservation using prepared statement
                        $product_name_snapshot = $item['name'] ?? 'Deleted Product';
                        $product_image_snapshot = $item['image'] ?? '';
                        $insert_stmt = $conn->prepare("INSERT INTO reservations (user_id, product_id, quantity, reservation_date, pickup_date, total_amount, notes, status, product_name_snapshot, product_image_snapshot, variation_id, variation_name, unit_price) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'pending', ?, ?, ?, ?, ?)");
                        // Types: i,i,i,s,d,s,s,s,i,s,d
                        $insert_stmt->bind_param(
                            "iiisdsssisd",
                            $user_id,
                            $product_id,
                            $quantity,
                            $pickup_date_escaped,
                            $subtotal,
                            $notes_escaped,
                            $product_name_snapshot,
                            $product_image_snapshot,
                            $item['variation_id'],
                            $variation_name,
                            $unit_price
                        );
                        @$insert_stmt->execute();
                        $insert_stmt->close();
                        
                        // Update stock using prepared statement
                        $stock_stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                        $stock_stmt->bind_param("ii", $quantity, $product_id);
                        $stock_stmt->execute();
                        $stock_stmt->close();
                    }
                    
                    // Clear only reserved cart items using prepared statement
                    $delete_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND id IN ($selected_ids_sql)");
                    $delete_stmt->bind_param("i", $user_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    
                    $conn->commit();
                    $success = "Reservation submitted successfully! You can track it in 'My Reservations'.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error processing reservation. Please try again.";
                }
            } else {
                $message = "Your cart is empty.";
            }
            $items_stmt->close();
        }
    }
}

// Get cart items - now includes variation price
$cart_stmt = $conn->prepare("SELECT c.*, p.name, p.price as base_price, p.stock, p.image, 
    COALESCE(pv.price, p.price) as price, 
    COALESCE(pv.variation_label, '') as variation_label
FROM cart c 
JOIN products p ON c.product_id = p.id 
LEFT JOIN product_variations pv ON c.variation_id = pv.id
WHERE c.user_id = ? 
ORDER BY c.created_at DESC");
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_items = $cart_stmt->get_result();

$total = 0;
$cart_data = [];
while ($item = $cart_items->fetch_assoc()) {
    $item['subtotal'] = $item['price'] * $item['quantity'];
    $total += $item['subtotal'];
    $cart_data[] = $item;
}
$cart_items->data_seek(0); // Reset pointer to use again in HTML
$item_count = count($cart_data);
$cart_stmt->close();

// Get pending reservations count
$pending_stmt = $conn->prepare("SELECT COUNT(*) as total FROM reservations WHERE user_id = ? AND status = 'pending'");
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_row = $pending_result->fetch_assoc();
$pending_count = $pending_row['total'] ?? 0;
$pending_stmt->close();

// Get notifications
$notif_stmt = $conn->prepare("SELECT r.*, COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name FROM reservations r LEFT JOIN products p ON r.product_id = p.id WHERE r.user_id = ? ORDER BY r.created_at DESC LIMIT 5");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();
$notif_stmt->close();

// Get default max
$max_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'max_reservations_per_day'");
$max_stmt->execute();
$max_result = $max_stmt->get_result();
$max_row = $max_result->fetch_assoc();
$default_max = $max_row ? (int)$max_row['setting_value'] : 5;
$max_stmt->close();

// Get date settings
$dateSettings = [];
$settingsResult = $conn->query("SELECT pickup_date, max_reservations FROM date_settings");
while ($row = $settingsResult->fetch_assoc()) {
    $dateSettings[$row['pickup_date']] = $row['max_reservations'];
}

// Get reservations by date
$reservationsByDate = [];
$result = $conn->query("SELECT COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY)) as pickup_date, COUNT(*) as count FROM reservations WHERE status IN ('pending', 'confirmed') GROUP BY COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY))");
while ($row = $result->fetch_assoc()) {
    $reservationsByDate[$row['pickup_date']] = $row['count'];
}

$default_pickup_date = date('Y-m-d', strtotime('+3 days'));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Shopping Cart - E-Reserve for Crochet Flowers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .cart-container { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 20px; }
        .cart-items { background: white; border-radius: 20px; padding: 28px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); border: 1px solid rgba(226, 232, 240, 0.6); }
        .cart-items h3 { margin: 0 0 24px 0; color: #1e293b; font-size: 20px; font-weight: 700; }
        .cart-item { display: flex; gap: 24px; padding: 24px; border-bottom: 1px solid #f1f5f9; transition: all 0.2s ease; border-radius: 12px; margin-bottom: 12px; }
        .cart-item.selected { background: linear-gradient(90deg, #fdf2f8 0%, #faf5ff 100%); }
        .cart-item:last-child { border-bottom: none; margin-bottom: 0; }
        .cart-item:hover { background: linear-gradient(90deg, #fdf2f8 0%, #faf5ff 100%); }
        .item-select { display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .cart-select-checkbox { width: 20px; height: 20px; accent-color: #f472b6; cursor: pointer; }
        .select-all-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 18px; margin-bottom: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; }
        .select-all-label { display: flex; align-items: center; gap: 10px; color: #334155; font-weight: 700; cursor: pointer; }
        .selected-count { color: #64748b; font-size: 13px; font-weight: 600; }
        .item-image { width: 120px; height: 120px; background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #fbcfe8 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 60px; flex-shrink: 0; }
        .item-details { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .item-details h4 { margin: 0; color: #1e293b; font-size: 18px; font-weight: 700; }
        .item-details .price { background: linear-gradient(135deg, #f472b6, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 800; font-size: 20px; }
        .item-details .stock { color: #16a34a; font-size: 13px; display: flex; align-items: center; gap: 6px; }
        .item-details .subtotal { font-weight: 700; color: #7c3aed; }
        .item-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 12px; }
        .quantity-form { display: flex; align-items: center; gap: 12px; }
        .quantity-form input { width: 80px; padding: 12px; border: 2px solid #e2e8f0; border-radius: 12px; text-align: center; font-weight: 600; font-size: 15px; transition: border-color 0.2s; }
        .quantity-form input:focus { outline: none; border-color: #f472b6; }
        .btn-update { padding: 10px 20px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; }
        .btn-update:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3); }
        .btn-remove { padding: 10px 20px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; }
        .btn-remove:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
        .checkout-section { background: white; border-radius: 20px; padding: 28px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); position: sticky; top: 20px; border: 1px solid rgba(226, 232, 240, 0.6); }
        .checkout-section h3 { margin: 0 0 24px 0; color: #1e293b; font-size: 20px; font-weight: 700; }
        .summary-row { display: flex; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #f1f5f9; }
        .summary-row:last-of-type { border-bottom: none; }
        .summary-row.total { font-size: 24px; font-weight: 800; background: linear-gradient(135deg, #f472b6, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; padding-top: 20px; margin-top: 10px; border-top: 2px solid #e2e8f0; }
        .checkout-form { margin-top: 24px; }
        .checkout-form label { display: block; margin-bottom: 8px; color: #334155; font-weight: 600; font-size: 14px; }
        .checkout-form textarea { width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; font-size: 14px; transition: border-color 0.2s; resize: vertical; min-height: 100px; }
        .checkout-form textarea:focus { outline: none; border-color: #f472b6; }
        .btn-checkout { width: 100%; padding: 16px; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3); }
        .btn-checkout:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4); }
        .btn-checkout:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }
        .empty-cart { text-align: center; padding: 80px 40px; background: white; border-radius: 20px; border: 1px solid rgba(226, 232, 240, 0.6); }
        .empty-cart .icon { font-size: 100px; margin-bottom: 24px; }
        .btn-continue { display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #f472b6, #c084fc); color: white; text-decoration: none; border-radius: 12px; margin-top: 24px; font-weight: 700; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3); }
        .btn-continue:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(244, 114, 182, 0.4); }
        .cart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .cart-header h3 { margin: 0; }
        .btn-remove-all { padding: 10px 20px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
        .btn-remove-all:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4); }
        .notification-bell { position: relative; cursor: pointer; padding: 10px; font-size: 24px; color: #64748b; transition: color 0.3s; }
        .notification-bell:hover { color: #f472b6; }
        .notification-badge { position: absolute; top: 0; right: 0; background: #ef4444; color: white; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 10px; min-width: 18px; text-align: center; }
        .notification-dropdown { display: none; position: fixed; top: 100px; right: 40px; width: 380px; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); z-index: 1001; overflow: hidden; max-height: 450px; overflow-y: auto; border: 2px solid #f472b6; }
        .notification-dropdown.show { display: block; }
        .notification-header { padding: 16px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
        .notification-item { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 12px; transition: background 0.2s; }
        .notification-item:hover { background: #f8fafc; }
        .notification-item:last-child { border-bottom: none; }
        .notification-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .notification-icon.pending { background: #fef3c7; color: #d97706; }
        .notification-icon.confirmed { background: #dbeafe; color: #2563eb; }
        .notification-icon.completed { background: #dcfce7; color: #16a34a; }
        .notification-icon.cancelled { background: #fee2e2; color: #dc2626; }
        .notification-content { flex: 1; }
        .notification-content h4 { margin: 0 0 4px 0; font-size: 14px; color: #1e293b; }
        .notification-content p { margin: 0; font-size: 12px; color: #64748b; }
        .notification-time { font-size: 11px; color: #94a3b8; margin-top: 4px; }
        .notification-empty { padding: 40px 20px; text-align: center; color: #94a3b8; }
        .notification-empty i { font-size: 48px; margin-bottom: 12px; display: block; }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .pickup-date-section { background: #f8fafc; border-radius: 16px; padding: 20px; margin-bottom: 16px; border: 2px solid #e2e8f0; }
        .pickup-date-section h4 { margin: 0 0 16px 0; color: #1e293b; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .pickup-calendar-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .pickup-calendar-nav button { padding: 6px 12px; background: #e2e8f0; color: #475569; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s; }
        .pickup-calendar-nav button:hover { background: #f472b6; color: white; }
        .pickup-calendar-month { font-weight: 700; color: #1e293b; font-size: 14px; }
        .pickup-calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 16px; }
        .pickup-calendar-header { text-align: center; padding: 8px; font-weight: 600; color: #64748b; font-size: 12px; }
        .pickup-calendar-day { aspect-ratio: 1; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; background: white; border: 2px solid transparent; min-height: 50px; font-size: 13px; }
        .pickup-calendar-day:hover:not(.disabled):not(.selected) { border-color: #f472b6; transform: scale(1.05); }
        .pickup-calendar-day.other-month { opacity: 0.3; cursor: default; }
        .pickup-calendar-day.disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
        .pickup-calendar-day.selected {
            background: linear-gradient(135deg, #3b82f6, #ec4899) !important;
            color: #ffffff !important;
            border-color: #2563eb !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25), 0 6px 16px rgba(236, 72, 153, 0.35);
            transform: scale(1.06);
            z-index: 1;
        }
        .pickup-calendar-day.selected .pickup-day-status,
        .pickup-calendar-day.selected .pickup-day-number {
            color: #ffffff !important;
        }
        .pickup-calendar-day.selected:hover {
            background: linear-gradient(135deg, #2563eb, #db2777) !important;
            border-color: #1d4ed8 !important;
        }
        .pickup-calendar-day.available { background: linear-gradient(135deg, #dcfce7, #bbf7d0); border-color: #22c55e; }
        .pickup-calendar-day.near-full { background: linear-gradient(135deg, #fef3c7, #fde68a); border-color: #f59e0b; }
        .pickup-calendar-day.full { background: linear-gradient(135deg, #fee2e2, #fecaca); border-color: #ef4444; cursor: not-allowed; }
        .pickup-day-number { font-weight: 700; font-size: 14px; }
        .pickup-day-status { font-size: 9px; margin-top: 2px; }
        .selected-date-display { background: white; border-radius: 12px; padding: 16px; border: 2px solid #f472b6; display: flex; align-items: center; gap: 12px; }
        .selected-date-display .date-icon { font-size: 32px; }
        .selected-date-display .date-info { flex: 1; }
        .selected-date-display .date-info h5 { margin: 0 0 4px 0; color: #1e293b; font-size: 16px; }
        .selected-date-display .date-info p { margin: 0; font-size: 13px; }
        .selected-date-display .date-info p.available { color: #16a34a; }
        .selected-date-display .date-info p.near-full { color: #d97706; }
        .selected-date-display .date-info p.full { color: #dc2626; }
        .pickup-calendar-legend { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; }
        .pickup-legend-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #64748b; }
        .pickup-legend-color { width: 14px; height: 14px; border-radius: 4px; }
        .pickup-legend-available { background: linear-gradient(135deg, #dcfce7, #bbf7d0); border: 1px solid #22c55e; }
        .pickup-legend-near-full { background: linear-gradient(135deg, #fef3c7, #fde68a); border: 1px solid #f59e0b; }
        .pickup-legend-full { background: linear-gradient(135deg, #fee2e2, #fecaca); border: 1px solid #ef4444; }
        @media (max-width: 1024px) { .cart-container { grid-template-columns: 1fr; } .checkout-section { position: static; } }
        @media (max-width: 600px) { .cart-item { flex-direction: column; align-items: center; text-align: center; } .item-select { align-self: flex-start; } .select-all-row { flex-direction: column; align-items: flex-start; } .item-image { width: 100px; height: 100px; } .item-actions { width: 100%; align-items: center; } .quantity-form { flex-direction: column; width: 100%; } .quantity-form input { width: 100%; } .btn-update, .btn-remove { width: 100%; } }
    </style>
</head>
<body>

<div class="container">
    <div class="sidebar">
        <h2>🌸 Crochet</h2>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="products.php"><i class="far fa-gem"></i> Products</a>
        <a href="cart.php" class="active"><i class="fas fa-shopping-cart"></i> Cart</a>
        <a href="reservations.php"><i class="fas fa-clipboard-list"></i> My Reservations</a>
        <a href="pickup_calendar.php"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            <h1>🛒 Shopping Cart</h1>
            <div class="topbar-right">
                <div class="notification-bell" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($pending_count > 0): ?><span class="notification-badge"><?php echo $pending_count; ?></span><?php endif; ?>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <span><i class="fas fa-bell"></i> Notifications</span>
                        <a href="reservations.php" style="color: white; text-decoration: none; font-size: 12px;">View All →</a>
                    </div>
                    <?php if ($notifications->num_rows > 0): ?>
                        <?php while ($notif = $notifications->fetch_assoc()): ?>
                            <div class="notification-item">
                                <div class="notification-icon <?php echo htmlspecialchars($notif['status']); ?>">
                                    <?php switch($notif['status']) { case 'pending': echo '⏳'; break; case 'confirmed': echo '✅'; break; case 'completed': echo '🎉'; break; case 'cancelled': echo '❌'; break; } ?>
                                </div>
                                <div class="notification-content">
                                    <h4>Order #<?php echo (int)$notif['id']; ?>: <?php echo ucfirst(htmlspecialchars($notif['status'])); ?></h4>
                                    <p><?php echo htmlspecialchars($notif['product_name']); ?> (x<?php echo (int)$notif['quantity']; ?>)</p>
                                    <div class="notification-time"><?php echo date('M d, Y - g:i A', strtotime($notif['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="notification-empty"><i class="fas fa-bell-slash"></i><p>No notifications yet</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        function toggleNotifications() { document.getElementById('notificationDropdown').classList.toggle('show'); }
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = document.querySelector('.notification-bell');
            if (!bell.contains(event.target) && !dropdown.contains(event.target)) { dropdown.classList.remove('show'); }
        });
        </script>

        <?php if ($success): ?>
            <div class="success-message" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; padding: 18px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2);"><span style="font-size: 24px;">🎉</span><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="error-message" style="background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; padding: 18px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500;"><span style="font-size: 24px;">❌</span><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($item_count > 0): ?>
            <div class="cart-container">
                <div class="cart-items">
                    <div class="cart-header">
                        <h3>🛒 Your Cart (<?php echo $item_count; ?> items)</h3>
                        <form method="POST" onsubmit="return confirm('⚠️ WARNING: You are about to remove ALL items from your cart!\n\nThis action cannot be undone!\n\nAre you absolutely sure?');">
                            <button type="submit" name="remove_all" class="btn-remove-all">🗑️ Remove All</button>
                        </form>
                    </div>
                    <div class="select-all-row">
                        <label class="select-all-label">
                            <input type="checkbox" id="selectAllCartItems" class="cart-select-checkbox" checked>
                            <span>Select all items for checkout</span>
                        </label>
                        <span class="selected-count" id="selectedCountText"><?php echo $item_count; ?> selected</span>
                    </div>
                    <?php foreach ($cart_data as $item): ?>
                        <div class="cart-item selected" data-cart-id="<?php echo (int)$item['id']; ?>" data-subtotal="<?php echo htmlspecialchars((string)$item['subtotal']); ?>">
                            <div class="item-select">
                                <input type="checkbox" class="cart-select-checkbox cart-item-checkbox" name="selected_cart_ids[]" value="<?php echo (int)$item['id']; ?>" form="checkoutForm" checked aria-label="Select <?php echo htmlspecialchars($item['name']); ?> for checkout">
                            </div>
                            <div class="item-image">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($item['image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:16px;">
                                <?php else: ?>
                                    🌸
                                <?php endif; ?>
                            </div>
                            <div class="item-details">
                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                <?php if (!empty($item['variation_label'])): ?>
                                    <div class="variation-label" style="font-size:13px;color:#7c3aed;font-weight:600;background:#f3e8ff;padding:4px 10px;border-radius:20px;display:inline-block;margin-bottom:8px;">
                                        📦 <?php echo htmlspecialchars($item['variation_label']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="price">₱<?php echo number_format($item['price'], 2); ?> each</div>
                                <div class="stock">✅ In Stock: <?php echo (int)$item['stock']; ?> available</div>
                                <div class="subtotal">Subtotal: ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                            </div>
                            <div class="item-actions">
                                <form method="POST" class="quantity-form">
                                    <input type="hidden" name="cart_id" value="<?php echo (int)$item['id']; ?>">
                                    <input type="number" name="quantity" value="<?php echo (int)$item['quantity']; ?>" min="1" max="<?php echo (int)$item['stock']; ?>">
                                    <button type="submit" name="update_cart" class="btn-update">Update</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('⚠️ Are you sure you want to remove this item from your cart?')">
                                    <input type="hidden" name="cart_id" value="<?php echo (int)$item['id']; ?>">
                                    <button type="submit" name="remove_item" class="btn-remove">Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="checkout-section">
                    <h3>📋 Order Summary</h3>
                    <div class="summary-row"><span>Selected Items (<span id="selectedItemsCount"><?php echo $item_count; ?></span>)</span><span id="selectedSubtotal">₱<?php echo number_format($total, 2); ?></span></div>
                    <div class="summary-row"><span>Reservation Fee</span><span>₱0.00</span></div>
                    <div class="summary-row total"><span>Total</span><span id="selectedTotal">₱<?php echo number_format($total, 2); ?></span></div>

                    <form method="POST" class="checkout-form" id="checkoutForm">
                        <div class="pickup-date-section">
                            <h4>📅 Select Pickup Date</h4>
                            <div class="pickup-calendar-nav">
                                <button type="button" onclick="changeMonth(-1)">← Prev</button>
                                <span class="pickup-calendar-month" id="pickupMonth">March 2026</span>
                                <button type="button" onclick="changeMonth(1)">Next →</button>
                            </div>
                            <div class="pickup-calendar-grid" id="pickupCalendarGrid">
                                <div class="pickup-calendar-header">Sun</div>
                                <div class="pickup-calendar-header">Mon</div>
                                <div class="pickup-calendar-header">Tue</div>
                                <div class="pickup-calendar-header">Wed</div>
                                <div class="pickup-calendar-header">Thu</div>
                                <div class="pickup-calendar-header">Fri</div>
                                <div class="pickup-calendar-header">Sat</div>
                            </div>
                            <div class="selected-date-display" id="selectedDateDisplay">
                                <span class="date-icon">📅</span>
                                <div class="date-info">
                                    <h5 id="selectedDateText">Select a date</h5>
                                    <p class="available" id="selectedDateStatus">Click on a date to select</p>
                                </div>
                            </div>
                            <div class="pickup-calendar-legend">
                                <div class="pickup-legend-item"><div class="pickup-legend-color pickup-legend-available"></div><span>Available</span></div>
                                <div class="pickup-legend-item"><div class="pickup-legend-color pickup-legend-near-full"></div><span>Nearly Full</span></div>
                                <div class="pickup-legend-item"><div class="pickup-legend-color pickup-legend-full"></div><span>Fully Booked</span></div>
                            </div>
                        </div>
                        <input type="hidden" name="pickup_date" id="pickup_date" value="<?php echo htmlspecialchars($default_pickup_date); ?>">
                        <label>Special Requests (Optional)</label>
                        <textarea name="notes" placeholder="Any special requests or notes..."></textarea>
                        <button type="submit" name="checkout" class="btn-checkout" id="checkoutBtn">✓ Reserve Now</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <div class="icon">🛒</div>
                <h2>Your cart is empty</h2>
                <p style="color: #64748b;">Looks like you haven't added any items to your cart yet.</p>
                <a href="products.php" class="btn-continue">🛍️ Browse Products</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const dateSettings = <?php echo json_encode($dateSettings); ?>;
const reservationsByDate = <?php echo json_encode($reservationsByDate); ?>;
const defaultMax = <?php echo $default_max; ?>;
let pickupMonth = new Date().getMonth();
let pickupYear = new Date().getFullYear();
let selectedPickupDate = "<?php echo htmlspecialchars($default_pickup_date); ?>";
let selectedDateIsFull = false;

function formatPeso(amount) {
    return '₱' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateCheckoutSelection() {
    const itemCheckboxes = Array.from(document.querySelectorAll('.cart-item-checkbox'));
    const selectedCheckboxes = itemCheckboxes.filter(checkbox => checkbox.checked);
    const selectedCount = selectedCheckboxes.length;
    const selectedTotal = selectedCheckboxes.reduce((sum, checkbox) => {
        const cartItem = checkbox.closest('.cart-item');
        return sum + Number(cartItem ? cartItem.dataset.subtotal : 0);
    }, 0);

    itemCheckboxes.forEach(checkbox => {
        const cartItem = checkbox.closest('.cart-item');
        if (cartItem) {
            cartItem.classList.toggle('selected', checkbox.checked);
        }
    });

    const selectAll = document.getElementById('selectAllCartItems');
    if (selectAll) {
        selectAll.checked = selectedCount === itemCheckboxes.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < itemCheckboxes.length;
    }

    const selectedItemsCount = document.getElementById('selectedItemsCount');
    const selectedSubtotal = document.getElementById('selectedSubtotal');
    const selectedTotalEl = document.getElementById('selectedTotal');
    const selectedCountText = document.getElementById('selectedCountText');

    if (selectedItemsCount) selectedItemsCount.textContent = selectedCount;
    if (selectedSubtotal) selectedSubtotal.textContent = formatPeso(selectedTotal);
    if (selectedTotalEl) selectedTotalEl.textContent = formatPeso(selectedTotal);
    if (selectedCountText) selectedCountText.textContent = `${selectedCount} selected`;

    const btn = document.getElementById('checkoutBtn');
    if (!btn) return;

    if (selectedCount < 1) {
        btn.disabled = true;
        btn.textContent = 'Select items to reserve';
    } else if (selectedDateIsFull) {
        btn.disabled = true;
        btn.textContent = '🚫 Date Fully Booked';
    } else {
        btn.disabled = false;
        btn.textContent = '✓ Reserve Now';
    }
}

function getMaxForDate(dateStr) { return dateSettings[dateStr] || defaultMax; }

function getAvailabilityStatus(dateStr) {
    const max = getMaxForDate(dateStr);
    const current = reservationsByDate[dateStr] || 0;
    const remaining = max - current;
    return { max, current, remaining, status: remaining <= 0 ? 'full' : (remaining <= 1 ? 'near-full' : 'available') };
}

function renderPickupCalendar() {
    const grid = document.getElementById('pickupCalendarGrid');
    const header = document.getElementById('pickupMonth');
    while (grid.children.length > 7) grid.removeChild(grid.lastChild);
    
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    header.textContent = `${months[pickupMonth]} ${pickupYear}`;
    
    const firstDay = new Date(pickupYear, pickupMonth, 1).getDay();
    const daysInMonth = new Date(pickupYear, pickupMonth + 1, 0).getDate();
    const daysInPrevMonth = new Date(pickupYear, pickupMonth, 0).getDate();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    for (let i = firstDay - 1; i >= 0; i--) {
        const day = document.createElement('div');
        day.className = 'pickup-calendar-day other-month';
        day.innerHTML = `<span class="pickup-day-number">${daysInPrevMonth - i}</span>`;
        grid.appendChild(day);
    }
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dayEl = document.createElement('div');
        dayEl.className = 'pickup-calendar-day';
        const dateStr = `${pickupYear}-${String(pickupMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const checkDate = new Date(pickupYear, pickupMonth, day);
        checkDate.setHours(0, 0, 0, 0);
        
        const isPast = checkDate < today;
        const isToday = checkDate.getTime() === today.getTime();
        const avail = getAvailabilityStatus(dateStr);
        
        if (isPast) {
            dayEl.classList.add('disabled');
            dayEl.innerHTML = `<span class="pickup-day-number">${day}</span>`;
        } else if (avail.status === 'full') {
            dayEl.classList.add('full');
            dayEl.innerHTML = `<span class="pickup-day-number">${day}</span><span class="pickup-day-status">FULL</span>`;
        } else if (avail.status === 'near-full') {
            dayEl.classList.add('near-full');
            dayEl.innerHTML = `<span class="pickup-day-number">${day}</span><span class="pickup-day-status">${avail.remaining} left</span>`;
        } else {
            dayEl.classList.add('available');
            dayEl.innerHTML = `<span class="pickup-day-number">${day}</span><span class="pickup-day-status">${avail.remaining} slots</span>`;
        }
        
        if (dateStr === selectedPickupDate) dayEl.classList.add('selected');
        
        if (!isPast && avail.status !== 'full') {
            dayEl.onclick = () => selectPickupDate(dateStr);
        }
        
        grid.appendChild(dayEl);
    }
    
    const totalCells = firstDay + daysInMonth;
    const remaining = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
    for (let day = 1; day <= remaining; day++) {
        const dayEl = document.createElement('div');
        dayEl.className = 'pickup-calendar-day other-month';
        dayEl.innerHTML = `<span class="pickup-day-number">${day}</span>`;
        grid.appendChild(dayEl);
    }
}

function changeMonth(delta) {
    pickupMonth += delta;
    if (pickupMonth > 11) { pickupMonth = 0; pickupYear++; }
    else if (pickupMonth < 0) { pickupMonth = 11; pickupYear--; }
    renderPickupCalendar();
}

function selectPickupDate(dateStr) {
    selectedPickupDate = dateStr;
    document.getElementById('pickup_date').value = dateStr;
    
    const avail = getAvailabilityStatus(dateStr);
    const date = new Date(dateStr + 'T00:00:00');
    const formatted = date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    document.getElementById('selectedDateText').textContent = formatted;
    const statusEl = document.getElementById('selectedDateStatus');
    statusEl.className = avail.status === 'full' ? 'full' : (avail.status === 'near-full' ? 'near-full' : 'available');
    statusEl.textContent = avail.status === 'full' ? '❌ Fully Booked' : (avail.status === 'near-full' ? `⚠️ Only ${avail.remaining} slot(s) available` : `✅ ${avail.remaining} slots available`);
    
    renderPickupCalendar();

    selectedDateIsFull = avail.status === 'full';
    updateCheckoutSelection();
}

const selectAllCartItems = document.getElementById('selectAllCartItems');
if (selectAllCartItems) {
    selectAllCartItems.addEventListener('change', () => {
        document.querySelectorAll('.cart-item-checkbox').forEach(checkbox => {
            checkbox.checked = selectAllCartItems.checked;
        });
        updateCheckoutSelection();
    });
}

document.querySelectorAll('.cart-item-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateCheckoutSelection);
});

const checkoutForm = document.getElementById('checkoutForm');
if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(event) {
        if (!document.querySelector('.cart-item-checkbox:checked')) {
            event.preventDefault();
            alert('Please select at least one cart item to reserve.');
        }
    });
}

renderPickupCalendar();

// Initialize button state properly on page load
const initialAvail = getAvailabilityStatus(selectedPickupDate);
selectedDateIsFull = initialAvail.status === 'full';

selectPickupDate(selectedPickupDate);
updateCheckoutSelection();
</script>

<script src="../assets/js/script.js"></script>
</body>
</html>
