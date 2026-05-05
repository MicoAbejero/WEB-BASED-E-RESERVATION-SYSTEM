<?php
include 'includes/db.php';

echo "<h2>Database Diagnostic</h2>";

// Check if pickup_date column exists
$result = $conn->query("DESCRIBE reservations");
$has_pickup_date = false;
while ($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'pickup_date') {
        $has_pickup_date = true;
        echo "<p style='color:green'>✅ pickup_date column EXISTS</p>";
        echo "<p>Type: " . $row['Type'] . "</p>";
        echo "<p>Null: " . $row['Null'] . "</p>";
        echo "<p>Default: " . ($row['Default'] ?? 'NONE') . "</p>";
    }
}

if (!$has_pickup_date) {
    echo "<p style='color:red'>❌ pickup_date column MISSING - Adding now...</p>";
    $conn->query("ALTER TABLE reservations ADD COLUMN pickup_date DATE DEFAULT NULL");
    echo "<p style='color:green'>Column added!</p>";
}

// Show ALL reservations with pickup_date values
echo "<h3>All Reservations (showing pickup_date):</h3>";
$res = $conn->query("SELECT id, user_id, product_id, quantity, reservation_date, pickup_date, status, total_amount FROM reservations ORDER BY id DESC");
if ($res->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>User</th><th>Product</th><th>Qty</th><th>Reserved</th><th>Pickup Date</th><th>Status</th><th>Amount</th></tr>";
    while ($row = $res->fetch_assoc()) {
        $pickup = $row['pickup_date'];
        $color = ($pickup === '0000-00-00' || empty($pickup)) ? 'style="color:red"' : 'style="color:green"';
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['product_id'] . "</td>";
        echo "<td>" . $row['quantity'] . "</td>";
        echo "<td>" . $row['reservation_date'] . "</td>";
        echo "<td $color>" . ($pickup ?? 'NULL') . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['total_amount'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No reservations found.</p>";
}

// Test with valid user
echo "<h3>Test Insert with valid user:</h3>";
$valid_user = $conn->query("SELECT id FROM users LIMIT 1")->fetch_assoc();
if ($valid_user) {
    $user_id = $valid_user['id'];
    $valid_product = $conn->query("SELECT id FROM products LIMIT 1")->fetch_assoc();
    $product_id = $valid_product['id'];
    
    $test_date = date('Y-m-d', strtotime('+3 days'));
    echo "<p>Testing with user_id=$user_id, product_id=$product_id, pickup_date=$test_date</p>";
    
    $result = $conn->query("INSERT INTO reservations (user_id, product_id, quantity, reservation_date, pickup_date, total_amount, status) VALUES ($user_id, $product_id, 1, CURDATE(), '$test_date', 100.00, 'pending')");
    if ($result) {
        echo "<p style='color:green'>✅ Test insert SUCCESS</p>";
        $test_id = $conn->insert_id;
        
        // Verify
        $verify = $conn->query("SELECT pickup_date FROM reservations WHERE id = $test_id")->fetch_assoc();
        echo "<p>Pickup Date in DB: <strong>" . $verify['pickup_date'] . "</strong></p>";
        
        // Delete test
        $conn->query("DELETE FROM reservations WHERE id = $test_id");
        echo "<p style='color:orange'>Test record deleted.</p>";
    } else {
        echo "<p style='color:red'>❌ Test insert FAILED: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:red'>No users found in database!</p>";
}
?>
