<?php
/**
 * Update Products Script for Tulips Crochet Shop
 * Run this file in browser: http://localhost/e-reserve-crochet/update_products.php
 */

include 'includes/db.php';

echo "<h1>🌸 Updating Tulips Crochet Products</h1>";

// Clear existing products
$conn->query("DELETE FROM products");
echo "<p>✓ Cleared existing products</p>";

// Products data based on the price list
$products = [
    // TULIPS - Big Size
    ['Tulips Crochet - 1 pc', 'Beautiful handmade tulips crochet flowers. Big size.', 85, 'Tulips', 50],
    ['Tulips Crochet - 3 pcs', 'Beautiful handmade tulips crochet flowers. Big size. Pack of 3.', 259, 'Tulips', 30],
    ['Tulips Crochet - 5 pcs', 'Beautiful handmade tulips crochet flowers. Big size. Pack of 5.', 450, 'Tulips', 20],
    
    // SUNFLOWER - Big Size
    ['Sunflower Crochet - 1 pc', 'Beautiful handmade sunflower crochet flowers. Big size.', 140, 'Sunflower', 50],
    ['Sunflower Crochet - 2 pcs', 'Beautiful handmade sunflower crochet flowers. Big size. Pack of 2.', 270, 'Sunflower', 30],
    ['Sunflower Crochet - 3 pcs', 'Beautiful handmade sunflower crochet flowers. Big size. Pack of 3.', 410, 'Sunflower', 20],
    
    // ROSE - Big Size
    ['Rose Crochet - 1 pc', 'Beautiful handmade rose crochet flowers. Big size.', 120, 'Roses', 50],
    ['Rose Crochet - 2 pcs', 'Beautiful handmade rose crochet flowers. Big size. Pack of 2.', 230, 'Roses', 30],
    ['Rose Crochet - 3 pcs', 'Beautiful handmade rose crochet flowers. Big size. Pack of 3.', 350, 'Roses', 20],
    
    // FILLERS
    ['Dried Lavender', 'Natural dried lavender for bouquet fillers.', 20, 'Fillers', 100],
    ['Myosotis Crochet', 'Delicate myosotis (forget-me-not) crochet flowers for fillers.', 10, 'Fillers', 100],
    ['Mini Lily Crochet', 'Cute mini lily crochet flowers for fillers.', 30, 'Fillers', 80],
    ['Lily of Valley Crochet', 'Elegant lily of the valley crochet flowers for fillers.', 80, 'Fillers', 50],
    
    // FUZZY WIRE FLOWERS
    ['Fuzzy Wire Lily', 'Beautiful fuzzy wire lily crochet flowers.', 120, 'Fuzzy Wire', 40],
    ['Fuzzy Wire Rose', 'Beautiful fuzzy wire rose crochet flowers.', 120, 'Fuzzy Wire', 40],
    ['Fuzzy Wire Gervera', 'Beautiful fuzzy wire gervera crochet flowers.', 120, 'Fuzzy Wire', 40],
    
    // BOUQUETS
    ['Big Sunflower Bouquet', 'Stunning bouquet with 4 pcs Eucalyptus, 3 pcs Sunflower, 5 pcs Mini Daisy', 350, 'Bouquets', 15],
    ['Tulips Bouquet', 'Beautiful bouquet with 2 pcs Eucalyptus, 3 pcs Tulips, 6 pcs Mini Flowers', 250, 'Bouquets', 15],
    ['Mini Sunflower Bouquet', 'Adorable mini bouquet with 1 pc Leaf, 2 pcs Eucalyptus, 3 pcs Mini Sunflower, 5 pcs Mini Flowers', 250, 'Bouquets', 15],
    ['Rose Bouquet', 'Classic rose bouquet with mixed fillers', 350, 'Bouquets', 15],
    ['Mixed Flower Bouquet', 'Beautiful mixed flower bouquet with assorted crochet flowers', 400, 'Bouquets', 10],
    
    // ACCESSORIES
    ['Gift Box - Small', 'Elegant small gift box for single flower or small bouquet', 50, 'Accessories', 50],
    ['Gift Box - Large', 'Elegant large gift box for bouquets', 100, 'Accessories', 30],
    ['Ribbon - Pink', 'Beautiful pink satin ribbon for wrapping', 30, 'Accessories', 100],
    ['Ribbon - White', 'Beautiful white satin ribbon for wrapping', 30, 'Accessories', 100],
    ['Ribbon - Gold', 'Beautiful gold satin ribbon for wrapping', 35, 'Accessories', 80],
    
    // CORSAGES
    ['Rose Corsage', 'Elegant rose corsage for special occasions', 150, 'Corsages', 20],
    ['Tulip Corsage', 'Beautiful tulip corsage for special occasions', 150, 'Corsages', 20],
    ['Sunflower Corsage', 'Cheerful sunflower corsage for special occasions', 180, 'Corsages', 15],
    
    // BASKETS
    ['Flower Basket - Small', 'Cute basket arrangement with crochet flowers', 300, 'Baskets', 15],
    ['Flower Basket - Medium', 'Medium basket arrangement with crochet flowers', 450, 'Baskets', 10],
    ['Flower Basket - Large', 'Large basket arrangement with crochet flowers', 600, 'Baskets', 8],
    
    // VASES
    ['Single Flower Vase', 'Elegant vase with single crochet flower', 200, 'Vases', 20],
    ['Multi Flower Vase', 'Beautiful vase with multiple crochet flowers', 350, 'Vases', 15],
    
    // DECORATIONS
    ['Flower Wreath - Small', 'Decorative crochet flower wreath for doors or walls', 400, 'Decorations', 10],
    ['Flower Wreath - Large', 'Large decorative crochet flower wreath', 550, 'Decorations', 8],
    ['Flower Crown', 'Beautiful crochet flower crown for events', 250, 'Decorations', 20],
    ['Table Centerpiece', 'Elegant crochet flower centerpiece for tables', 500, 'Decorations', 10],
    
    // SPECIAL OCCASIONS
    ['Wedding Bouquet', 'Custom wedding bouquet with crochet flowers', 1200, 'Special Occasions', 5],
    ['Birthday Bouquet', 'Festive birthday bouquet with crochet flowers', 450, 'Special Occasions', 15],
    ['Anniversary Bouquet', 'Romantic anniversary bouquet with crochet flowers', 500, 'Special Occasions', 10],
    ['Graduation Bouquet', 'Celebratory graduation bouquet', 400, 'Special Occasions', 15],
];

// Insert products
$count = 0;
foreach ($products as $product) {
    $name = $conn->real_escape_string($product[0]);
    $description = $conn->real_escape_string($product[1]);
    $price = $product[2];
    $category = $conn->real_escape_string($product[3]);
    $stock = $product[4];
    
    $sql = "INSERT INTO products (name, description, price, category, stock, is_available, created_at) 
            VALUES ('$name', '$description', $price, '$category', $stock, 1, NOW())";
    
    if ($conn->query($sql)) {
        $count++;
    } else {
        echo "<p style='color:red;'>Error inserting: $name - " . $conn->error . "</p>";
    }
}

echo "<h2>✅ Successfully added $count products!</h2>";
echo "<p><a href='admin/products.php' style='background: #f472b6; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none;'>View Products in Admin →</a></p>";
echo "<p><a href='user/products.php' style='background: #22c55e; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none;'>View Products in Store →</a></p>";
?>
