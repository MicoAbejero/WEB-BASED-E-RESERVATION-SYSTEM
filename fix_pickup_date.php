<?php
/**
 * Fix script to add pickup_date column to reservations table
 * Run this by accessing: http://localhost/e-reserve-crochet/fix_pickup_date.php
 */

include 'includes/db.php';

echo "<h2>🔧 Pickup Date Column Fix</h2>";
echo "<pre>";

// Check if column exists
$db_name = 'e_reserve_db'; // Using hardcoded db name
$result = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = '$db_name' 
    AND TABLE_NAME = 'reservations' 
    AND COLUMN_NAME = 'pickup_date'
");
$row = $result->fetch_assoc();

if ($row['cnt'] == 0) {
    echo "❌ pickup_date column NOT found. Adding it now...\n";
    
    // Add the column
    if ($conn->query("ALTER TABLE reservations ADD COLUMN pickup_date DATE DEFAULT NULL AFTER reservation_date")) {
        echo "✅ Successfully added pickup_date column!\n";
    } else {
        echo "❌ Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "✅ pickup_date column already exists!\n";
}

// Update existing reservations with NULL pickup_date to use reservation_date + 3 days
echo "\n📝 Updating existing reservations with missing pickup dates...\n";
$update_result = $conn->query("
    UPDATE reservations 
    SET pickup_date = DATE_ADD(reservation_date, INTERVAL 3 DAY)
    WHERE pickup_date IS NULL OR pickup_date = '0000-00-00'
");
echo "Updated " . $conn->affected_rows . " reservations\n";

// Show table structure
echo "\n📋 Current reservations table structure:\n";
$result = $conn->query("DESCRIBE reservations");
while ($col = $result->fetch_assoc()) {
    echo "  - {$col['Field']}: {$col['Type']} " . ($col['Null'] === 'YES' ? '(NULL)' : '(NOT NULL)') . "\n";
}

// Show sample data
echo "\n📊 Sample reservation data:\n";
$result = $conn->query("SELECT id, reservation_date, pickup_date, status FROM reservations LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  ID #{$row['id']}: Reserved={$row['reservation_date']}, Pickup={$row['pickup_date']}, Status={$row['status']}\n";
    }
} else {
    echo "  No reservations found.\n";
}

echo "\n✅ Fix completed! You can now access the admin reservations page.\n";
echo "</pre>";
echo "<p><a href='admin/reservations.php'>➡️ Go to Reservations Management</a></p>";
echo "<p><a href='user/reservations.php'>➡️ Go to My Reservations (User)</a></p>";
?>
