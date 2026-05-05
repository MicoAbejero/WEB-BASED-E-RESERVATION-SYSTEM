<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    die("Please login as customer first");
}

$user_id = (int)$_SESSION['user_id'];

// Simulate a checkout with debug
if (isset($_POST['debug_checkout'])) {
    echo "<h2>Debug Checkout Results</h2>";
    
    $pickup_date = $_POST['pickup_date'] ?? 'NOT SET';
    $pickup_date_raw = $_POST['pickup_date'] ?? '';
    
    echo "<h3>POST Data Received:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>Pickup Date Analysis:</h3>";
    echo "<ul>";
    echo "<li>Raw value: '$pickup_date_raw'</li>";
    echo "<li>Is empty: " . (empty($pickup_date_raw) ? 'YES' : 'NO') . "</li>";
    echo "<li>Is NULL: " . ($pickup_date_raw === 'NULL' ? 'YES' : 'NO') . "</li>";
    echo "<li>preg_match result: " . (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickup_date_raw) ? 'VALID FORMAT' : 'INVALID FORMAT') . "</li>";
    echo "<li>strtotime: " . strtotime($pickup_date_raw) . "</li>";
    echo "<li>Date formatted: " . date('Y-m-d', strtotime($pickup_date_raw)) . "</li>";
    echo "</ul>";
    
    if (!empty($pickup_date_raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickup_date_raw)) {
        $pickup_date_escaped = $conn->real_escape_string($pickup_date_raw);
        
        // Get valid product
        $product = $conn->query("SELECT id FROM products LIMIT 1")->fetch_assoc();
        $product_id = $product['id'];
        
        $insert_sql = "INSERT INTO reservations (user_id, product_id, quantity, reservation_date, pickup_date, total_amount, status) 
                       VALUES ($user_id, $product_id, 1, CURDATE(), '$pickup_date_escaped', 100.00, 'pending')";
        
        echo "<h3>Insert SQL:</h3>";
        echo "<p>$insert_sql</p>";
        
        $result = $conn->query($insert_sql);
        if ($result) {
            $test_id = $conn->insert_id;
            echo "<p style='color:green'>✅ Insert SUCCESS (ID: $test_id)</p>";
            
            // Verify
            $check = $conn->query("SELECT pickup_date FROM reservations WHERE id = $test_id")->fetch_assoc();
            echo "<p>Stored pickup_date: <strong>" . $check['pickup_date'] . "</strong></p>";
            
            // Delete test
            $conn->query("DELETE FROM reservations WHERE id = $test_id");
            echo "<p style='color:orange'>Test record deleted.</p>";
        } else {
            echo "<p style='color:red'>❌ Insert FAILED: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Invalid or empty pickup_date - not inserting</p>";
    }
    
    echo "<hr>";
}

// Show current cart
echo "<h2>Debug Checkout - Current Cart</h2>";
$cart = $conn->query("SELECT c.*, p.name, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = $user_id");
if ($cart->num_rows > 0) {
    echo "<form method='POST'>";
    echo "<input type='hidden' name='debug_checkout' value='1'>";
    echo "<p>Pickup Date (from hidden input):</p>";
    
    // Show what's in the hidden field
    $default_date = date('Y-m-d', strtotime('+3 days'));
    echo "<input type='hidden' name='pickup_date' id='pickup_date' value='$default_date'>";
    echo "<p>Hidden field value: <strong id='debug_date'>$default_date</strong></p>";
    
    echo "<p><a href='user/cart.php'>Go to Cart Page</a> - Use that to checkout</p>";
    echo "</form>";
} else {
    echo "<p>Your cart is empty.</p>";
    echo "<p><a href='user/products.php'>Go to Products</a></p>";
}
?>
