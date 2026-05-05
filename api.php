<?php
header('Content-Type: application/json');
include 'includes/db.php';
include 'includes/auth.php';

$response = ['success' => false, 'message' => ''];

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
        @$conn->query("ALTER TABLE reservations ADD COLUMN $col_name $col_def");
    }
}

$action = $_POST['action'] ?? '';

switch ($action) {
    
    // Get availability data for pickup calendar
    case 'get_availability':
        $default_max = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'max_reservations_per_day'")->fetch_assoc()['setting_value'] ?? 5;
        
        // Get date-specific settings
        $dateSettings = [];
        $settingsResult = $conn->query("SELECT pickup_date, max_reservations FROM date_settings");
        while ($row = $settingsResult->fetch_assoc()) {
            $dateSettings[$row['pickup_date']] = $row['max_reservations'];
        }
        
        // Get reservation counts by date
        $reservationsByDate = [];
        $result = $conn->query("SELECT COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY)) as pickup_date,
                                       COUNT(*) as count
                                FROM reservations 
                                WHERE status IN ('pending', 'confirmed') 
                                AND COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY)) >= CURDATE() 
                                GROUP BY COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY))");
        while ($row = $result->fetch_assoc()) {
            $reservationsByDate[$row['pickup_date']] = $row['count'];
        }
        
        // Build availability array for next 60 days
        $availability = [];
        for ($i = 1; $i <= 60; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $maxForDate = $dateSettings[$date] ?? $default_max;
            $currentCount = $reservationsByDate[$date] ?? 0;
            $remaining = $maxForDate - $currentCount;
            
            $availability[$date] = [
                'max' => $maxForDate,
                'current' => $currentCount,
                'remaining' => max(0, $remaining),
                'status' => $remaining <= 0 ? 'full' : ($remaining <= 1 ? 'near_full' : 'available')
            ];
        }
        
        $response['success'] = true;
        $response['data'] = $availability;
        break;
    // ========== USER ACTIONS ==========
    
    // Cancel reservation
    case 'cancel_reservation':
        if (!has_permission('reservation.cancel')) {
            $response['message'] = 'Please login first';
            break;
        }
        
        $user_id = $_SESSION['user_id'];
        $reservation_id = (int)$_POST['reservation_id'];
        
        $res = $conn->query("SELECT * FROM reservations WHERE id = $reservation_id AND user_id = $user_id AND status = 'pending'");
        
        if ($res->num_rows > 0) {
            $reservation = $res->fetch_assoc();
            
            // Only restore stock if product still exists
            $product_check = $conn->query("SELECT id FROM products WHERE id = {$reservation['product_id']}");
            if ($product_check->num_rows > 0) {
                $conn->query("UPDATE products SET stock = stock + {$reservation['quantity']} WHERE id = {$reservation['product_id']}");
            }
            
            // Always cancel reservation even if product was deleted
            $conn->query("UPDATE reservations SET status = 'cancelled', last_updated_by=$user_id, last_updated_by_role='customer', last_updated_at=NOW() WHERE id = $reservation_id");
            
            $response['success'] = true;
            $response['message'] = 'Reservation cancelled successfully!';
        } else {
            $response['message'] = 'Reservation not found or cannot be cancelled';
        }
        break;
    
    // Update cart quantity
    case 'update_cart_quantity':
        if (!has_permission('cart.manage_own')) {
            $response['message'] = 'Please login first';
            break;
        }
        
        $user_id = $_SESSION['user_id'];
        $cart_id = (int)$_POST['cart_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity > 0) {
            $conn->query("UPDATE cart SET quantity = $quantity WHERE id = $cart_id AND user_id = $user_id");
            $response['success'] = true;
            $response['message'] = 'Cart updated!';
        } else {
            $conn->query("DELETE FROM cart WHERE id = $cart_id AND user_id = $user_id");
            $response['success'] = true;
            $response['message'] = 'Item removed from cart!';
        }
        break;
    
    // Remove from cart
    case 'remove_from_cart':
        if (!has_permission('cart.manage_own')) {
            $response['message'] = 'Please login first';
            break;
        }
        
        $user_id = $_SESSION['user_id'];
        $cart_id = (int)$_POST['cart_id'];
        
        $conn->query("DELETE FROM cart WHERE id = $cart_id AND user_id = $user_id");
        $response['success'] = true;
        $response['message'] = 'Item removed from cart!';
        break;
    
    // Checkout/Reserve
    case 'checkout':
        if (!has_permission('reservation.create')) {
            $response['message'] = 'Please login first';
            break;
        }
        
        $user_id = $_SESSION['user_id'];
        $pickup_date = $_POST['pickup_date'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        // Default to 3 days from now if empty
        if (empty($pickup_date)) {
            $pickup_date = date('Y-m-d', strtotime('+3 days'));
        }
        
        // Validate and normalize pickup date format
        $pickup_date = date('Y-m-d', strtotime($pickup_date));
        if ($pickup_date === '1970-01-01' || $pickup_date === '') {
            $pickup_date = date('Y-m-d', strtotime('+3 days'));
        }
        
        $cart_items = $conn->query("SELECT c.*, p.price, p.stock, p.name 
                                    FROM cart c 
                                    JOIN products p ON c.product_id = p.id 
                                    WHERE c.user_id = $user_id");
        
        if ($cart_items->num_rows > 0) {
            $conn->begin_transaction();
            
            try {
                while ($item = $cart_items->fetch_assoc()) {
                    $subtotal = $item['price'] * $item['quantity'];
                    
                    $conn->query("INSERT INTO reservations (user_id, product_id, quantity, reservation_date, pickup_date, total_amount, notes, status)
                                 VALUES ($user_id, {$item['product_id']}, {$item['quantity']}, CURDATE(), '$pickup_date', $subtotal, '$notes', 'pending')");
                    
                    $conn->query("UPDATE products SET stock = stock - {$item['quantity']} WHERE id = {$item['product_id']}");
                }
                
                $conn->query("DELETE FROM cart WHERE user_id = $user_id");
                $conn->commit();
                
                $response['success'] = true;
                $response['message'] = 'Reservation submitted successfully!';
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'Error processing reservation. Please try again.';
            }
        } else {
            $response['message'] = 'Your cart is empty.';
        }
        break;
    
    // ========== ADMIN ACTIONS ==========
    
    // Update reservation status
    case 'update_reservation_status':
        if (!has_permission('reservation.manage')) {
            $response['message'] = 'Access denied';
            break;
        }
        
        $admin_id = (int)$_SESSION['user_id'];
        $reservation_id = (int)$_POST['reservation_id'];
        $new_status = $_POST['status'];
        $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');
        
        if (in_array($new_status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
            if ($new_status == 'cancelled' && $cancellation_reason === '') {
                $response['message'] = 'Cancellation reason is required';
                break;
            }

            $res = $conn->query("SELECT * FROM reservations WHERE id = $reservation_id")->fetch_assoc();
            if (!$res) {
                $response['message'] = 'Reservation not found';
                break;
            }

            // If cancelling, restore stock
            if ($new_status == 'cancelled' && in_array($res['status'], ['pending', 'confirmed'])) {
                $product_check = $conn->query("SELECT id FROM products WHERE id = {$res['product_id']}");
                if ($product_check->num_rows > 0) {
                    $conn->query("UPDATE products SET stock = stock + {$res['quantity']} WHERE id = {$res['product_id']}");
                }
            }
            
            $reason_sql = '';
            if ($new_status == 'cancelled') {
                $reason = $conn->real_escape_string($cancellation_reason);
                $reason_sql = ", cancellation_reason='$reason'";
            }

            $conn->query("UPDATE reservations SET status = '$new_status', last_updated_by=$admin_id, last_updated_by_role='admin', last_updated_at=NOW()$reason_sql WHERE id = $reservation_id");
            $response['success'] = true;
            $response['message'] = 'Status updated successfully!';
        } else {
            $response['message'] = 'Invalid status';
        }
        break;
    
    // Add product
    case 'add_product':
        if (!has_permission('product.manage')) {
            $response['message'] = 'Access denied';
            break;
        }
        
        $name = $_POST['name'];
        $description = $_POST['description'] ?? '';
        $price = (float)$_POST['price'];
        $category = $_POST['category'];
        $stock = (int)$_POST['stock'];
        
        $conn->query("INSERT INTO products (name, description, price, category, stock, is_available) 
                      VALUES ('$name', '$description', $price, '$category', $stock, 1)");
        $response['success'] = true;
        $response['message'] = 'Product added successfully!';
        break;
    
    // Update product
    case 'update_product':
        if (!has_permission('product.manage')) {
            $response['message'] = 'Access denied';
            break;
        }
        
        $id = (int)$_POST['product_id'];
        $name = $_POST['name'];
        $description = $_POST['description'] ?? '';
        $price = (float)$_POST['price'];
        $category = $_POST['category'];
        $stock = (int)$_POST['stock'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        $conn->query("UPDATE products SET name='$name', description='$description', price=$price, 
                      category='$category', stock=$stock, is_available=$is_available WHERE id=$id");
        $response['success'] = true;
        $response['message'] = 'Product updated successfully!';
        break;
    
    // Delete product
    case 'delete_product':
        if (!has_permission('product.manage')) {
            $response['message'] = 'Access denied';
            break;
        }
        
        $id = (int)$_POST['product_id'];
        $conn->query("DELETE FROM products WHERE id=$id");
        $response['success'] = true;
        $response['message'] = 'Product deleted successfully!';
        break;
    
    default:
        $response['message'] = 'Invalid action';
}

echo json_encode($response);
exit();
