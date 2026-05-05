-- Update Products Price List for Tulips Crochet Shop
-- Run this SQL file in phpMyAdmin or MySQL

-- Clear existing products
DELETE FROM products;

-- Insert Tulips Crochet Products
INSERT INTO products (name, description, price, category, stock, is_available, created_at) VALUES

-- TULIPS CROCHET - Big Size
('Tulips Crochet - 1 pc', 'Beautiful handmade tulips crochet flowers. Big size.', 85, 'Tulips', 50, 1, NOW()),
('Tulips Crochet - 3 pcs', 'Beautiful handmade tulips crochet flowers. Big size. Pack of 3.', 259, 'Tulips', 30, 1, NOW()),
('Tulips Crochet - 5 pcs', 'Beautiful handmade tulips crochet flowers. Big size. Pack of 5.', 450, 'Tulips', 20, 1, NOW()),

-- SUNFLOWER CROCHET - Big Size
('Sunflower Crochet - 1 pc', 'Beautiful handmade sunflower crochet flowers. Big size.', 140, 'Sunflower', 50, 1, NOW()),
('Sunflower Crochet - 2 pcs', 'Beautiful handmade sunflower crochet flowers. Big size. Pack of 2.', 270, 'Sunflower', 30, 1, NOW()),
('Sunflower Crochet - 3 pcs', 'Beautiful handmade sunflower crochet flowers. Big size. Pack of 3.', 410, 'Sunflower', 20, 1, NOW()),

-- ROSE CROCHET - Big Size
('Rose Crochet - 1 pc', 'Beautiful handmade rose crochet flowers. Big size.', 120, 'Roses', 50, 1, NOW()),
('Rose Crochet - 2 pcs', 'Beautiful handmade rose crochet flowers. Big size. Pack of 2.', 230, 'Roses', 30, 1, NOW()),
('Rose Crochet - 3 pcs', 'Beautiful handmade rose crochet flowers. Big size. Pack of 3.', 350, 'Roses', 20, 1, NOW()),

-- FILLERS
('Dried Lavender', 'Natural dried lavender for bouquet fillers.', 20, 'Fillers', 100, 1, NOW()),
('Myosotis Crochet', 'Delicate myosotis (forget-me-not) crochet flowers for fillers.', 10, 'Fillers', 100, 1, NOW()),
('Mini Lily Crochet', 'Cute mini lily crochet flowers for fillers.', 30, 'Fillers', 80, 1, NOW()),
('Lily of Valley Crochet', 'Elegant lily of the valley crochet flowers for fillers.', 80, 'Fillers', 50, 1, NOW()),

-- FUZZY WIRE FLOWERS
('Fuzzy Wire Lily', 'Beautiful fuzzy wire lily crochet flowers.', 120, 'Fuzzy Wire', 40, 1, NOW()),
('Fuzzy Wire Rose', 'Beautiful fuzzy wire rose crochet flowers.', 120, 'Fuzzy Wire', 40, 1, NOW()),
('Fuzzy Wire Gervera', 'Beautiful fuzzy wire gervera crochet flowers.', 120, 'Fuzzy Wire', 40, 1, NOW()),

-- BOUQUET CATEGORY
('Big Sunflower Bouquet', 'Stunning bouquet with 4 pcs Eucalyptus, 3 pcs Sunflower, 5 pcs Mini Daisy', 350, 'Bouquets', 15, 1, NOW()),
('Tulips Bouquet', 'Beautiful bouquet with 2 pcs Eucalyptus, 3 pcs Tulips, 6 pcs Mini Flowers', 250, 'Bouquets', 15, 1, NOW()),
('Mini Sunflower Bouquet', 'Adorable mini bouquet with 1 pc Leaf, 2 pcs Eucalyptus, 3 pcs Mini Sunflower, 5 pcs Mini Flowers', 250, 'Bouquets', 15, 1, NOW()),

-- Additional Bouquets
('Rose Bouquet', 'Classic rose bouquet with mixed fillers', 350, 'Bouquets', 15, 1, NOW()),
('Mixed Flower Bouquet', 'Beautiful mixed flower bouquet with assorted crochet flowers', 400, 'Bouquets', 10, 1, NOW()),

-- Accessories
('Gift Box - Small', 'Elegant small gift box for single flower or small bouquet', 50, 'Accessories', 50, 1, NOW()),
('Gift Box - Large', 'Elegant large gift box for bouquets', 100, 'Accessories', 30, 1, NOW()),
('Ribbon - Pink', 'Beautiful pink satin ribbon for wrapping', 30, 'Accessories', 100, 1, NOW()),
('Ribbon - White', 'Beautiful white satin ribbon for wrapping', 30, 'Accessories', 100, 1, NOW()),
('Ribbon - Gold', 'Beautiful gold satin ribbon for wrapping', 35, 'Accessories', 80, 1, NOW()),

-- Corsages
('Rose Corsage', 'Elegant rose corsage for special occasions', 150, 'Corsages', 20, 1, NOW()),
('Tulip Corsage', 'Beautiful tulip corsage for special occasions', 150, 'Corsages', 20, 1, NOW()),
('Sunflower Corsage', 'Cheerful sunflower corsage for special occasions', 180, 'Corsages', 15, 1, NOW()),

-- Baskets
('Flower Basket - Small', 'Cute basket arrangement with crochet flowers', 300, 'Baskets', 15, 1, NOW()),
('Flower Basket - Medium', 'Medium basket arrangement with crochet flowers', 450, 'Baskets', 10, 1, NOW()),
('Flower Basket - Large', 'Large basket arrangement with crochet flowers', 600, 'Baskets', 8, 1, NOW()),

-- Vases
('Single Flower Vase', 'Elegant vase with single crochet flower', 200, 'Vases', 20, 1, NOW()),
('Multi Flower Vase', 'Beautiful vase with multiple crochet flowers', 350, 'Vases', 15, 1, NOW()),

-- Decorations
('Flower Wreath - Small', 'Decorative crochet flower wreath for doors or walls', 400, 'Decorations', 10, 1, NOW()),
('Flower Wreath - Large', 'Large decorative crochet flower wreath', 550, 'Decorations', 8, 1, NOW()),
('Flower Crown', 'Beautiful crochet flower crown for events', 250, 'Decorations', 20, 1, NOW()),
('Table Centerpiece', 'Elegant crochet flower centerpiece for tables', 500, 'Decorations', 10, 1, NOW()),

-- Special Occasions
('Wedding Bouquet', 'Custom wedding bouquet with crochet flowers', 1200, 'Special Occasions', 5, 1, NOW()),
('Birthday Bouquet', 'Festive birthday bouquet with crochet flowers', 450, 'Special Occasions', 15, 1, NOW()),
('Anniversary Bouquet', 'Romantic anniversary bouquet with crochet flowers', 500, 'Special Occasions', 10, 1, NOW()),
('Graduation Bouquet', 'Celebratory graduation bouquet', 400, 'Special Occasions', 15, 1, NOW());

SELECT 'Products updated successfully!' AS message;
