<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "e_reserve_db"; // this must match your imported database name

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Lightweight schema safety updates for reservation product snapshots
$conn->query("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS product_name_snapshot VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS product_image_snapshot VARCHAR(255) DEFAULT NULL");

// Ensure product_id can be detached when a product is deleted
$nullable_check = $conn->query("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
                                WHERE TABLE_SCHEMA = '$dbname'
                                AND TABLE_NAME = 'reservations'
                                AND COLUMN_NAME = 'product_id'");
if ($nullable_check && $nullable_check->num_rows > 0) {
    $nullable_row = $nullable_check->fetch_assoc();
    if (($nullable_row['IS_NULLABLE'] ?? 'NO') === 'NO') {
        $conn->query("ALTER TABLE reservations MODIFY product_id INT NULL");
    }
}

// Auto-create variations table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS product_variations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variation_name VARCHAR(100) NOT NULL,
    variation_label VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)");

// Add variation columns to reservations if not exists
$conn->query("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS variation_id INT DEFAULT NULL");
$conn->query("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS variation_name VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10, 2) DEFAULT NULL");

// Add variation columns to cart if not exists
$conn->query("ALTER TABLE cart ADD COLUMN IF NOT EXISTS variation_id INT DEFAULT NULL");
$conn->query("ALTER TABLE cart ADD COLUMN IF NOT EXISTS variation_name VARCHAR(100) DEFAULT NULL");

// Insert default variations for existing products that don't have any
$conn->query("INSERT INTO product_variations (product_id, variation_name, variation_label, price, is_default)
SELECT id, '1pc', '1 Piece', price, TRUE FROM products
WHERE id NOT IN (SELECT DISTINCT product_id FROM product_variations)");
?>