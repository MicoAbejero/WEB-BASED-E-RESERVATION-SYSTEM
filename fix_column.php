<?php
include 'includes/db.php';

echo "<h2>Fixing pickup_date Column</h2>";

// Check current column definition
$result = $conn->query("DESCRIBE reservations");
echo "<h3>Current reservations table structure:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'pickup_date') {
        echo "<tr style='background:#ffdddd'>";
    } else {
        echo "<tr>";
    }
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . ($row['Default'] ?? 'NONE') . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Attempting to fix pickup_date column...</h3>";

// Try to modify the column
$fix_sql = "ALTER TABLE reservations MODIFY COLUMN pickup_date DATE NULL DEFAULT NULL";
$result = $conn->query($fix_sql);
if ($result) {
    echo "<p style='color:green'>✅ Column modified successfully!</p>";
} else {
    echo "<p style='color:red'>❌ Modify failed: " . $conn->error . "</p>";
}

// Verify the change
$result = $conn->query("DESCRIBE reservations");
while ($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'pickup_date') {
        echo "<p><strong>New column definition:</strong></p>";
        echo "<ul>";
        echo "<li>Type: " . $row['Type'] . "</li>";
        echo "<li>Null: " . $row['Null'] . "</li>";
        echo "<li>Default: " . ($row['Default'] ?? 'NONE') . "</li>";
        echo "</ul>";
    }
}

// Test inserting a valid date
echo "<h3>Testing insert with valid date:</h3>";
$valid_user = $conn->query("SELECT id FROM users LIMIT 1")->fetch_assoc();
$valid_product = $conn->query("SELECT id FROM products LIMIT 1")->fetch_assoc();

if ($valid_user && $valid_product) {
    $user_id = $valid_user['id'];
    $product_id = $valid_product['id'];
    $test_date = date('Y-m-d', strtotime('+3 days'));
    
    $insert_sql = "INSERT INTO reservations (user_id, product_id, quantity, reservation_date, pickup_date, total_amount, status) VALUES ($user_id, $product_id, 1, CURDATE(), '$test_date', 100.00, 'pending')";
    
    echo "<p>SQL: $insert_sql</p>";
    
    $result = $conn->query($insert_sql);
    if ($result) {
        $test_id = $conn->insert_id;
        echo "<p style='color:green'>✅ Insert SUCCESS</p>";
        
        // Check what was stored
        $check = $conn->query("SELECT pickup_date FROM reservations WHERE id = $test_id")->fetch_assoc();
        echo "<p>Stored pickup_date: <strong>" . $check['pickup_date'] . "</strong></p>";
        
        // Update existing records with NULL
        echo "<h3>Updating existing reservations with NULL pickup_date...</h3>";
        $update = $conn->query("UPDATE reservations SET pickup_date = NULL WHERE pickup_date = '0000-00-00' OR pickup_date IS NULL");
        echo "<p>Updated " . $conn->affected_rows . " records</p>";
        
        // Delete test record
        $conn->query("DELETE FROM reservations WHERE id = $test_id");
        echo "<p style='color:orange'>Test record deleted.</p>";
    } else {
        echo "<p style='color:red'>❌ Insert FAILED: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:red'>No users or products found!</p>";
}

echo "<h3>Final verification - Recent reservations:</h3>";
$res = $conn->query("SELECT id, pickup_date, status FROM reservations ORDER BY id DESC LIMIT 5");
while ($row = $res->fetch_assoc()) {
    $val = $row['pickup_date'];
    $display = ($val === '0000-00-00' || empty($val)) ? 'NULL/EMPTY' : $val;
    $color = ($val === '0000-00-00' || empty($val)) ? 'red' : 'green';
    echo "<p style='color:$color'>ID " . $row['id'] . ": pickup_date = " . $display . " (raw: " . $val . ")</p>";
}
?>
