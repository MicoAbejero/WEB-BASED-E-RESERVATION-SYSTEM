<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

// AJAX endpoint for loading variations
if (isset($_GET['ajax']) && $_GET['ajax'] === 'variations' && isset($_GET['product_id'])) {
    $pid = (int)$_GET['product_id'];
    $result = $conn->query("SELECT * FROM product_variations WHERE product_id = $pid ORDER BY is_default DESC, price ASC");
    $vars = [];
    while ($row = $result->fetch_assoc()) {
        $vars[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($vars);
    exit;
}
require_permission('product.manage', '../login.php');

$message = "";
$success = "";
$error = "";

// Add image column if not exists
$conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS image VARCHAR(255)");

// Auto-create variations table
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

// Add variation columns to reservations
$conn->query("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS variation_id INT DEFAULT NULL");
$conn->query("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS variation_name VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10, 2) DEFAULT NULL");

// Add variation columns to cart
$conn->query("ALTER TABLE cart ADD COLUMN IF NOT EXISTS variation_id INT DEFAULT NULL");
$conn->query("ALTER TABLE cart ADD COLUMN IF NOT EXISTS variation_name VARCHAR(100) DEFAULT NULL");

// Handle add variation
if (isset($_POST['add_variation'])) {
    $product_id = (int)$_POST['product_id'];
    $variation_label = $conn->real_escape_string(trim($_POST['variation_label']));
    $variation_name = strtolower(preg_replace('/\s+/', '_', $variation_label));
    $price = (float)$_POST['variation_price'];
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    if ($variation_label && $price > 0) {
        // If this is default, unset other defaults
        if ($is_default) {
            $conn->query("UPDATE product_variations SET is_default = 0 WHERE product_id = $product_id");
        }
        
        $conn->query("INSERT INTO product_variations (product_id, variation_name, variation_label, price, is_default) 
                      VALUES ($product_id, '$variation_name', '$variation_label', $price, $is_default)");
        $success = "✓ Variation added successfully!";
    } else {
        $error = "⚠️ Please provide a valid label and price.";
    }
}

// Handle set default variation
if (isset($_POST['set_default_variation'])) {
    $variation_id = (int)$_POST['variation_id'];
    $product_id = (int)$_POST['product_id'];
    $conn->query("UPDATE product_variations SET is_default = 0 WHERE product_id = $product_id");
    $conn->query("UPDATE product_variations SET is_default = 1 WHERE id = $variation_id");
    $success = "✓ Default variation updated!";
}

// Handle delete variation
if (isset($_POST['delete_variation'])) {
    $variation_id = (int)$_POST['variation_id'];
    $conn->query("DELETE FROM product_variations WHERE id = $variation_id");
    $success = "✓ Variation deleted successfully!";
}

// Handle add product with image
if (isset($_POST['add_product'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $stock = (int)$_POST['stock'];
    
    // Handle image upload
    $image_path = "";
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $upload_dir = '../assets/images/products/';
        
        // Create directory if not exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['product_image']['name']);
        $target_path = $upload_dir . $file_name;
        
        // Validate image
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($_FILES['product_image']['tmp_name']);
        
        if (in_array($file_type, $allowed_types)) {
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) {
                $image_path = 'assets/images/products/' . $file_name;
            }
        }
    }
    
    if (empty($error)) {
        if ($image_path) {
            $conn->query("INSERT INTO products (name, description, price, category, stock, is_available, image) 
                          VALUES ('$name', '$description', $price, '$category', $stock, 1, '$image_path')");
        } else {
            $conn->query("INSERT INTO products (name, description, price, category, stock, is_available) 
                          VALUES ('$name', '$description', $price, '$category', $stock, 1)");
        }
        
        $new_product_id = $conn->insert_id;
        
        // Handle variations if provided
        if (!empty($_POST['variations_json'])) {
            $variations = json_decode($_POST['variations_json'], true);
            if (is_array($variations)) {
                foreach ($variations as $v) {
                    $label = $conn->real_escape_string($v['label']);
                    $var_name = strtolower(preg_replace('/\s+/', '_', $label));
                    $var_price = (float)$v['price'];
                    $is_default = (int)$v['is_default'];
                    
                    $conn->query("INSERT INTO product_variations (product_id, variation_name, variation_label, price, is_default) 
                                  VALUES ($new_product_id, '$var_name', '$label', $var_price, $is_default)");
                }
            }
        }
        
        $success = "✓ Product added successfully!";
    }
}

// Handle update product
if (isset($_POST['update_product'])) {
    $id = (int)$_POST['product_id'];
    $name = $conn->real_escape_string($_POST['name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $stock = (int)$_POST['stock'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    $image_update = "";
    $current_product = $conn->query("SELECT image FROM products WHERE id = $id")->fetch_assoc();
    $current_image = $current_product['image'] ?? '';

    if (isset($_FILES['edit_product_image']) && $_FILES['edit_product_image']['error'] == 0) {
        $upload_dir = '../assets/images/products/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($_FILES['edit_product_image']['name']);
        $target_path = $upload_dir . $file_name;
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($_FILES['edit_product_image']['tmp_name']);

        if (in_array($file_type, $allowed_types)) {
            if (move_uploaded_file($_FILES['edit_product_image']['tmp_name'], $target_path)) {
                $new_image_path = 'assets/images/products/' . $file_name;
                $escaped_new_image_path = $conn->real_escape_string($new_image_path);
                $image_update = ", image = '$escaped_new_image_path'";

                if (!empty($current_image) && strpos($current_image, 'assets/images/products/') === 0 && file_exists('../' . $current_image)) {
                    unlink('../' . $current_image);
                }
            }
        }
    }
    
    $conn->query("UPDATE products SET name='$name', description='$description', price=$price, 
                  category='$category', stock=$stock, is_available=$is_available $image_update WHERE id=$id");
    
    // Handle variations if provided
    if (!empty($_POST['edit_variations_json'])) {
        $variations = json_decode($_POST['edit_variations_json'], true);
        if (is_array($variations)) {
            // Get existing variation ids
            $existing_ids = [];
            $res = $conn->query("SELECT id FROM product_variations WHERE product_id = $id");
            while ($row = $res->fetch_assoc()) {
                $existing_ids[] = $row['id'];
            }
            
            $submitted_existing_ids = [];
            
            // Process each variation
            foreach ($variations as $v) {
                $label = $conn->real_escape_string($v['label']);
                $var_name = strtolower(preg_replace('/\s+/', '_', $label));
                $var_price = (float)$v['price'];
                $is_default = (int)$v['is_default'];
                
                if ($is_default) {
                    // Unset all other defaults
                    $conn->query("UPDATE product_variations SET is_default = 0 WHERE product_id = $id");
                }
                
                if (!empty($v['existing']) && !empty($v['id'])) {
                    // Update existing variation
                    $vid = (int)$v['id'];
                    $submitted_existing_ids[] = $vid;
                    $conn->query("UPDATE product_variations SET 
                                  variation_label = '$label', 
                                  variation_name = '$var_name',
                                  price = $var_price,
                                  is_default = $is_default
                                  WHERE id = $vid AND product_id = $id");
                } else {
                    // Insert new variation
                    $conn->query("INSERT INTO product_variations (product_id, variation_name, variation_label, price, is_default) 
                                  VALUES ($id, '$var_name', '$label', $var_price, $is_default)");
                }
            }
            
            // Delete variations that were removed
            foreach ($existing_ids as $eid) {
                if (!in_array($eid, $submitted_existing_ids)) {
                    $conn->query("DELETE FROM product_variations WHERE id = $eid AND product_id = $id");
                }
            }
        }
    }
    
    $success = "✓ Product updated successfully!";
}

// Handle delete product
if (isset($_POST['delete_product'])) {
    $id = (int)$_POST['product_id'];

    $product = $conn->query("SELECT id, name, image FROM products WHERE id = $id")->fetch_assoc();
    if ($product) {
        $product_name = $conn->real_escape_string($product['name'] ?? 'Deleted Product');
        $product_image = $conn->real_escape_string($product['image'] ?? '');

        $conn->query("UPDATE reservations
                      SET product_name_snapshot = COALESCE(product_name_snapshot, '$product_name'),
                          product_image_snapshot = COALESCE(product_image_snapshot, '$product_image'),
                          product_id = NULL
                      WHERE product_id = $id");

        $conn->query("DELETE FROM cart WHERE product_id = $id");

        if ($product['image'] && file_exists('../' . $product['image'])) {
            unlink('../' . $product['image']);
        }

        $conn->query("DELETE FROM products WHERE id=$id");
        $success = "✓ Product deleted successfully! Existing reservations were preserved.";
    } else {
        $error = "⚠️ Product not found.";
    }
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get products with search filter
if ($search) {
    $search_escaped = $conn->real_escape_string($search);
    $products = $conn->query("SELECT * FROM products WHERE name LIKE '%$search_escaped%' OR category LIKE '%$search_escaped%' ORDER BY created_at DESC");
} else {
    $products = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
}

// Stats
$total_products = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];
$in_stock = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock > 0")->fetch_assoc()['total'];
$out_of_stock = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock = 0")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Products Management - E-Reserve Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%); min-height: 100vh; }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; height: 100vh; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); color: white; position: fixed; padding-top: 20px; box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15); }
        .sidebar h2 { text-align: center; margin-bottom: 30px; font-size: 24px; }
        .sidebar a { display: flex; align-items: center; gap: 12px; padding: 14px 24px; color: #94a3b8; text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent; font-size: 15px; }
        .sidebar a:hover { background: linear-gradient(90deg, #334155 0%, transparent 100%); color: #ffffff; border-left: 4px solid #f472b6; padding-left: 28px; }
        .sidebar a.active { background: linear-gradient(90deg, #334155 0%, transparent 100%); border-left: 4px solid #f472b6; color: white; }
        
        /* Main */
        .main { margin-left: 260px; padding: 30px 40px; width: 100%; }
        
        .topbar { background: white; padding: 20px 28px; margin-bottom: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); display: flex; align-items: center; justify-content: space-between; }
        .topbar h1 { margin: 0; font-size: 24px; font-weight: 600; color: #1e293b; }
        
        .page-header { margin-bottom: 30px; }
        .page-header h2 { margin: 0 0 10px 0; font-size: 28px; color: #1e293b; font-weight: 700; }
        .page-header p { margin: 0; color: #64748b; font-size: 15px; }
        
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        
        .btn-add { padding: 14px 28px; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 15px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3); display: flex; align-items: center; gap: 8px; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4); }
        
        /* Search and Filter */
        .search-filter-container {
            background: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.6);
        }
        
        .search-form {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .search-input-wrapper {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        
        .search-input-wrapper input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
        }
        
        .search-input-wrapper input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        
        .search-input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 18px;
        }
        
        .btn-search {
            padding: 14px 28px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }
        
        .btn-clear {
            padding: 14px 20px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .btn-clear:hover {
            background: #e2e8f0;
        }
        
        /* Cards */
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .card { background: white; padding: 28px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid rgba(226, 232, 240, 0.6); }
        .card .card-icon { font-size: 40px; margin-bottom: 16px; }
        .card h3 { margin: 0; font-size: 14px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .card p { font-size: 32px; font-weight: 800; margin: 12px 0 0 0; color: #1e293b; }
        
        /* Table */
        .products-table { width: 100%; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid rgba(226, 232, 240, 0.6); margin-top: 24px; }
        .products-table table { width: 100%; border-collapse: collapse; }
        .products-table th { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 18px 16px; text-align: left; color: #475569; font-weight: 700; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        .products-table td { padding: 18px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        .products-table tbody tr:hover { background: linear-gradient(90deg, #fdf2f8 0%, #faf5ff 100%); }
        
        .product-image-cell { width: 60px; height: 60px; border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #fdf2f8, #fce7f3); display: flex; align-items: center; justify-content: center; }
        .product-image-cell img { width: 100%; height: 100%; object-fit: cover; }
        .product-image-cell .no-image { font-size: 24px; }
        
        .status-available { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .status-unavailable { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .price-col { font-weight: 700; color: #7c3aed; font-size: 15px; }
        
        .action-btns { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-edit { padding: 8px 16px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.3s ease; }
        .btn-edit:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3); }
        .btn-delete { padding: 8px 16px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.3s ease; }
        .btn-delete:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
        .btn-variations { padding: 8px 16px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.3s ease; white-space: nowrap; }
        .btn-variations:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3); }
        
        .success-message { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; padding: 18px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2); }
        .error-message { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; padding: 18px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2); }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 99999; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        
        .modal-box { background: white; padding: 32px; border-radius: 20px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3); }
        .modal-box h2 { margin: 0 0 24px 0; color: #1e293b; font-size: 22px; font-weight: 700; }
        
        /* Variations Modal - wider */
        .modal-box.variations-modal { max-width: 680px; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; color: #334155; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; transition: all 0.2s ease; box-sizing: border-box; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #f472b6; box-shadow: 0 0 0 3px rgba(244, 114, 182, 0.2); }
        .form-group textarea { resize: vertical; min-height: 80px; }
        
        .image-upload { border: 2px dashed #e2e8f0; border-radius: 12px; padding: 40px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: #f8fafc; }
        .image-upload:hover { border-color: #f472b6; background: #fdf2f8; }
        .image-upload .icon { font-size: 48px; margin-bottom: 12px; color: #f472b6; }
        .image-upload .text { color: #64748b; font-size: 14px; font-weight: 500; }
        .image-upload input { display: none; }
        .image-preview { margin-top: 15px; padding: 15px; background: #f8fafc; border-radius: 12px; text-align: center; }
        .image-preview img { max-width: 200px; max-height: 150px; border-radius: 8px; object-fit: cover; }
        .image-preview .file-name { margin-top: 8px; font-size: 12px; color: #64748b; }
        
        .current-image { margin-top: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; }
        .current-image img { max-width: 100px; max-height: 80px; border-radius: 8px; margin-top: 8px; }
        
        .checkbox-group { display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 10px; }
        .checkbox-group input { width: 20px; height: 20px; cursor: pointer; }
        .checkbox-group label { margin: 0; cursor: pointer; }
        
        .modal-btns { display: flex; gap: 12px; margin-top: 28px; }
        .btn-submit { flex: 1; padding: 14px; background: linear-gradient(135deg, #f472b6, #c084fc); color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 15px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3); }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(244, 114, 182, 0.4); }
        .btn-cancel { flex: 1; padding: 14px; background: #f1f5f9; color: #64748b; border: none; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.3s ease; }
        .btn-cancel:hover { background: #e2e8f0; color: #475569; }
        
        /* Image Preview Modal */
        .image-modal { display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); }
        .image-modal.show { display: flex; align-items: center; justify-content: center; }
        .image-modal-content { max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 10px 50px rgba(0,0,0,0.5); }
        .image-modal-close { position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .image-modal-close:hover { color: #f472b6; }
        .product-image-cell { width: 60px; height: 60px; border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #fdf2f8, #fce7f3); display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .product-image-cell img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .product-image-cell:hover img { transform: scale(1.1); }

        /* Variations UI */
.variations-section { margin-top: 0; }
        .variations-section h3 { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px; }
.variation-add-form { background: #f8fafc; border-radius: 14px; padding: 20px; border: 2px dashed #e2e8f0; margin-bottom: 20px; }
        .variation-add-form h4 { margin: 0 0 14px 0; font-size: 14px; color: #475569; font-weight: 600; }
        .variation-add-row { display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; align-items: end; }
        .variation-add-row .form-group { margin-bottom: 0; }
        .btn-add-variation { padding: 12px 20px; background: linear-gradient(135deg, #f472b6, #c084fc); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 14px; white-space: nowrap; transition: all 0.3s; }
        .btn-add-variation:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3); }
        
        .variations-list { display: flex; flex-direction: column; gap: 10px; }
        .variation-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: white; border-radius: 12px; border: 2px solid #e2e8f0; transition: all 0.2s; }
        .variation-item:hover { border-color: #f472b6; }
        .variation-item.is-default { border-color: #22c55e; background: linear-gradient(135deg, #f0fdf4, #dcfce7); }
        .variation-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .variation-badge.default { background: #dcfce7; color: #15803d; }
        .variation-badge.regular { background: #f1f5f9; color: #64748b; }
        .variation-label { font-weight: 700; color: #1e293b; font-size: 15px; flex: 1; }
        .variation-price { font-weight: 800; color: #7c3aed; font-size: 16px; }
        .variation-actions { display: flex; gap: 8px; }
        .btn-set-default { padding: 6px 12px; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s; }
        .btn-set-default:hover { transform: translateY(-1px); }
        .btn-del-variation { padding: 6px 12px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s; }
        .btn-del-variation:hover { transform: translateY(-1px); }
        
.no-variations { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; background: #f8fafc; border-radius: 12px; border: 2px dashed #e2e8f0; }
        .no-variations i { font-size: 32px; display: block; margin-bottom: 8px; }

        .variation-count-badge { display: inline-flex; align-items: center; gap: 4px; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    </style>
</head>
<body>

<div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🌸 Crochet Admin</h2>
        <a href="dashboard.php"><i class="far fa-chart-bar"></i> Dashboard</a>
        <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
        <a href="products.php" class="active"><i class="far fa-gem"></i> Products</a>
        <a href="reservations.php"><i class="fas fa-clipboard-list"></i> Reservations</a>
        <a href="pickup_calendar.php"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">
        <div class="topbar">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</h1>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #22c55e; font-weight: 500;">
                <span style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%; animation: pulse 2s infinite;"></span>
                Live
            </div>
        </div>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2>🧶 Products Management</h2>
                <p>Manage your crochet flower inventory and size variations</p>
            </div>
            <button class="btn-add" onclick="openAddModal()">
                <span>➕</span> Add New Product
            </button>
        </div>

        <div class="cards">
            <div class="card">
                <div class="card-icon">🧶</div>
                <h3>Total Products</h3>
                <p><?php echo $total_products; ?></p>
            </div>
            <div class="card">
                <div class="card-icon">✅</div>
                <h3>Available</h3>
                <p><?php echo $in_stock; ?></p>
            </div>
            <div class="card">
                <div class="card-icon">❌</div>
                <h3>Unavailable</h3>
                <p><?php echo $out_of_stock; ?></p>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter-container">
            <form method="GET" class="search-form" id="searchForm">
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="searchInput" placeholder="Search by name or category..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn-search">🔍 Search</button>
                <a href="products.php" class="btn-clear">Clear</a>
            </form>
        </div>

        <div class="products-table">
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Base Price</th>
                        <th>Variations</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($product = $products->fetch_assoc()):
                        // Get variation count for this product
                        $var_count_res = $conn->query("SELECT COUNT(*) as cnt FROM product_variations WHERE product_id = " . (int)$product['id']);
                        $var_count = $var_count_res->fetch_assoc()['cnt'];
                    ?>
                        <tr>
                            <td>
                                <?php if ($product['image']): ?>
                                    <div class="product-image-cell" onclick="openImageModal('../<?php echo htmlspecialchars($product['image']); ?>')">
                                        <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="product-image-cell">
                                        <span class="no-image">🧶</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td class="price-col">₱<?php echo number_format($product['price'], 2); ?></td>
                            <td>
                                <?php if ($var_count > 0): ?>
                                    <span class="variation-count-badge">🎨 <?php echo $var_count; ?> size<?php echo $var_count > 1 ? 's' : ''; ?></span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 13px;">No variations</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $product['stock']; ?></td>
                            <td>
                                <?php if ($product['is_available']): ?>
                                    <span class="status-available">Available</span>
                                <?php else: ?>
                                    <span class="status-unavailable">Unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-edit" onclick='editProduct(<?php echo json_encode($product); ?>)'>Edit</button>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');" style="display:inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" name="delete_product" class="btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php if ($products->num_rows == 0): ?>
                <div style="padding: 40px; text-align: center; color: #64748b;">
                    <?php if ($search): ?>
                        <p>No products found matching "<?php echo htmlspecialchars($search); ?>"</p>
                    <?php else: ?>
                        <p>No products found.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div id="addModal" class="modal-overlay">
    <div class="modal-box">
        <h2>➕ Add New Product</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Product Image</label>
                <label class="image-upload">
                    <input type="file" name="product_image" accept="image/*" onchange="previewAddImage(this)">
                    <div class="icon"><i class="fas fa-camera"></i></div>
                    <div class="text">Click to upload image</div>
                    <div class="text" style="font-size: 12px; color: #94a3b8;">JPG, PNG, GIF, or WEBP</div>
                </label>
                <div class="image-preview" id="add_preview_container" style="display: none;">
                    <img id="add_preview_img" src="" alt="Preview">
                    <div class="file-name" id="add_file_name"></div>
                </div>
            </div>
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name" required placeholder="Enter product name">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Enter product description"></textarea>
            </div>
            <div class="form-group">
                <label>Base Price (₱) *</label>
                <input type="number" name="price" step="0.01" required placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Category *</label>
                <input type="text" name="category" required placeholder="Enter category (e.g., Bouquets, Arrangements, etc.)" list="category-list">
                <datalist id="category-list">
                    <option value="Bouquets">
                    <option value="Arrangements">
                    <option value="Vases">
                    <option value="Corsages">
                    <option value="Baskets">
                    <option value="Decorations">
                    <option value="Plants">
                    <option value="Special Occasions">
                </datalist>
            </div>
            <div class="form-group">
                <label>Stock Quantity *</label>
                <input type="number" name="stock" required placeholder="0" min="0">
            </div>
            <hr style="border: none; border-top: 2px dashed #e2e8f0; margin: 24px 0;">
            
            <!-- Product Variations Section in Add Form -->
            <div>
                <h3><i class="fas fa-palette"></i> Product Sizes / Variations <span style="font-size: 13px; color: #94a3b8; font-weight: normal;">(Optional - add if product has different sizes/prices)</span></h3>
                
                <div class="variation-add-form">
                    <h4>➕ Add Size / Variation</h4>
                    <div class="variation-add-row">
                        <div class="form-group">
                            <label>Size Label</label>
                            <input type="text" id="new_var_label" placeholder="e.g. Small, Medium, Large...">
                        </div>
                        <div class="form-group">
                            <label>Price (₱)</label>
                            <input type="number" id="new_var_price" step="0.01" min="0.01" placeholder="0.00">
                        </div>
                        <button type="button" class="btn-add-variation" onclick="addTempVariation()">+ Add</button>
                    </div>
                </div>
                
                <div class="variations-list" id="tempVariationsList">
                    <div class="no-variations"><i class="fas fa-tags"></i>No sizes added yet. You can add sizes later if needed.</div>
                </div>
                
                <input type="hidden" name="variations_json" id="variations_json" value="">
            </div>

            <div class="modal-btns">
                <button type="submit" name="add_product" class="btn-submit">Add Product</button>
                <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <h2>✏️ Edit Product</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="product_id" id="edit_id">
            <div class="form-group">
                <label>Product Image (leave empty to keep current)</label>
                <label class="image-upload">
                    <input type="file" name="edit_product_image" id="edit_product_image" accept="image/*" onchange="previewEditImage(this)">
                    <div class="icon"><i class="fas fa-camera"></i></div>
                    <div class="text">Click to upload new image</div>
                    <div class="text" style="font-size: 12px; color: #94a3b8;">JPG, PNG, GIF, or WEBP</div>
                </label>
                <div class="image-preview" id="edit_preview_container" style="display: none;">
                    <img id="edit_preview_img" src="" alt="Preview">
                    <div class="file-name" id="edit_file_name"></div>
                </div>
                <div class="current-image" id="current_image_container" style="display:none; margin-top: 10px;">
                    <span>Current image:</span>
                    <img id="current_image_preview" src="" alt="Current Image" style="max-width: 100px; max-height: 80px; border-radius: 8px; margin-top: 8px; display: block;">
                </div>
            </div>
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="edit_description"></textarea>
            </div>
            <div class="form-group">
                <label>Base Price (₱) *</label>
                <input type="number" name="price" id="edit_price" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Category *</label>
                <input type="text" name="category" id="edit_category" required placeholder="Enter category">
            </div>
            <div class="form-group">
                <label>Stock Quantity *</label>
                <input type="number" name="stock" id="edit_stock" required min="0">
            </div>
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="is_available" id="edit_available">
                    <label for="edit_available">Available for sale</label>
                </div>
            </div>
            
            <hr style="border: none; border-top: 2px dashed #e2e8f0; margin: 24px 0;">
            
            <!-- Product Variations Section in Edit Form -->
            <div>
                <h3><i class="fas fa-palette"></i> Product Sizes / Variations</h3>
                
                <div class="variation-add-form">
                    <h4>➕ Add Size / Variation</h4>
                    <div class="variation-add-row">
                        <div class="form-group">
                            <label>Size Label</label>
                            <input type="text" id="edit_new_var_label" placeholder="e.g. Small, Medium, Large...">
                        </div>
                        <div class="form-group">
                            <label>Price (₱)</label>
                            <input type="number" id="edit_new_var_price" step="0.01" min="0.01" placeholder="0.00">
                        </div>
                        <button type="button" class="btn-add-variation" onclick="addEditTempVariation()">+ Add</button>
                    </div>
                </div>
                
                <div class="variations-list" id="editVariationsList">
                    <div class="no-variations"><i class="fas fa-tags"></i>Loading sizes...</div>
                </div>
                
                <input type="hidden" name="edit_variations_json" id="edit_variations_json" value="">
            </div>

            <div class="modal-btns">
                <button type="submit" name="update_product" class="btn-submit">Update Product</button>
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Variations Modal -->
<div id="variationsModal" class="modal-overlay">
    <div class="modal-box variations-modal">
        <h2>🎨 Size Variations — <span id="varProductName" style="color:#f472b6;"></span></h2>
        <p style="color:#64748b; margin: -16px 0 20px 0; font-size: 14px;">Set different sizes and prices for this product. Customers will choose a size when adding to cart.</p>
        
        <!-- Add Variation Form -->
        <div class="variation-add-form">
            <h4>➕ Add New Size / Variation</h4>
            <form method="POST" id="addVariationForm">
                <input type="hidden" name="product_id" id="var_product_id">
                <div class="variation-add-row">
                    <div class="form-group">
                        <label>Size / Label *</label>
                        <input type="text" name="variation_label" placeholder="e.g. Small, Medium, Large, 1 Piece, 3 Pieces..." required>
                    </div>
                    <div class="form-group">
                        <label>Price (₱) *</label>
                        <input type="number" name="variation_price" step="0.01" min="0.01" placeholder="0.00" required>
                    </div>
                    <button type="submit" name="add_variation" class="btn-add-variation">+ Add</button>
                </div>
                <div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_default" id="var_is_default">
                        <label for="var_is_default">Set as default selection</label>
                    </div>
                </div>
            </form>
        </div>

        <!-- Existing Variations List -->
        <div>
            <h3><i class="fas fa-list"></i> Current Variations <span id="varCountBadge"></span></h3>
            <div class="variations-list" id="variationsList">
                <div class="no-variations"><i class="fas fa-tags"></i>No variations yet. Add one above!</div>
            </div>
        </div>

        <div class="modal-btns" style="margin-top: 24px;">
            <button type="button" class="btn-cancel" onclick="closeModal('variationsModal')" style="flex: none; padding: 14px 32px;">✓ Done</button>
        </div>
    </div>
</div>

<script>
// Live search without page refresh
const productSearchInput = document.getElementById('searchInput');
const productRows = Array.from(document.querySelectorAll('.products-table tbody tr'));

if (productSearchInput) {
    productSearchInput.addEventListener('input', function() {
        const term = this.value.trim().toLowerCase();
        let visibleCount = 0;

        productRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const match = term === '' || text.includes(term);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        let liveNoResult = document.getElementById('live-no-result-row');
        if (visibleCount === 0 && productRows.length > 0) {
            if (!liveNoResult) {
                liveNoResult = document.createElement('tr');
                liveNoResult.id = 'live-no-result-row';
                liveNoResult.innerHTML = '<td colspan="8" style="text-align:center; padding: 30px; color:#64748b;">No matching products.</td>';
                document.querySelector('.products-table tbody').appendChild(liveNoResult);
            }
        } else if (liveNoResult) {
            liveNoResult.remove();
        }
    });
}

function openAddModal() {
    document.getElementById('addModal').classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function previewAddImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('add_preview_img').src = e.target.result;
            document.getElementById('add_preview_container').style.display = 'block';
            document.getElementById('add_file_name').textContent = input.files[0].name;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function previewEditImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('edit_preview_img').src = e.target.result;
            document.getElementById('edit_preview_container').style.display = 'block';
            document.getElementById('edit_file_name').textContent = input.files[0].name;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function editProduct(product) {
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_description').value = product.description || '';
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_category').value = product.category;
    document.getElementById('edit_stock').value = product.stock;
    document.getElementById('edit_available').checked = product.is_available == 1;
    document.getElementById('edit_product_image').value = '';
    document.getElementById('edit_preview_container').style.display = 'none';
    
    if (product.image) {
        document.getElementById('current_image_container').style.display = 'block';
        document.getElementById('current_image_preview').src = '../' + product.image;
    } else {
        document.getElementById('current_image_container').style.display = 'none';
    }
    
    document.getElementById('editModal').classList.add('show');
}

// ===================== VARIATIONS MODAL =====================
let currentVariationProductId = null;

function openVariationsModal(productId, productName) {
    currentVariationProductId = productId;
    document.getElementById('varProductName').textContent = productName;
    document.getElementById('var_product_id').value = productId;
    document.getElementById('variationsModal').classList.add('show');
    loadVariations(productId);
}

function loadVariations(productId) {
    fetch('?ajax=variations&product_id=' + productId)
        .then(r => r.json())
        .then(data => {
            renderVariations(data);
        })
        .catch(() => {
            document.getElementById('variationsList').innerHTML = '<div class="no-variations"><i class="fas fa-exclamation-circle"></i>Error loading variations.</div>';
        });
}

function renderVariations(variations) {
    const list = document.getElementById('variationsList');
    const badge = document.getElementById('varCountBadge');
    
    if (!variations || variations.length === 0) {
        list.innerHTML = '<div class="no-variations"><i class="fas fa-tags"></i><br>No size variations yet.<br><small>Add one above to offer different sizes and prices!</small></div>';
        badge.innerHTML = '';
        return;
    }
    
    badge.innerHTML = `<span class="variation-count-badge">${variations.length} size${variations.length > 1 ? 's' : ''}</span>`;
    
    list.innerHTML = variations.map(v => `
        <div class="variation-item ${v.is_default == 1 ? 'is-default' : ''}">
            <span class="variation-badge ${v.is_default == 1 ? 'default' : 'regular'}">${v.is_default == 1 ? '⭐ Default' : 'Option'}</span>
            <span class="variation-label">${escHtml(v.variation_label)}</span>
            <span class="variation-price">₱${parseFloat(v.price).toFixed(2)}</span>
            <div class="variation-actions">
                ${v.is_default != 1 ? `
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="variation_id" value="${v.id}">
                    <input type="hidden" name="product_id" value="${v.product_id}">
                    <button type="submit" name="set_default_variation" class="btn-set-default" title="Set as default">⭐ Default</button>
                </form>` : ''}
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this variation?')">
                    <input type="hidden" name="variation_id" value="${v.id}">
                    <button type="submit" name="delete_variation" class="btn-del-variation">🗑️</button>
                </form>
            </div>
        </div>
    `).join('');
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('show');
    }
    if (event.target.classList.contains('image-modal')) {
        event.target.classList.remove('show');
    }
}

function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').classList.add('show');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('show');
}

// ===================== TEMP VARIATIONS IN ADD FORM =====================
let tempVariations = [];

function addTempVariation() {
    const label = document.getElementById('new_var_label').value.trim();
    const price = parseFloat(document.getElementById('new_var_price').value);
    
    if (!label || isNaN(price) || price <= 0) {
        alert('Please enter valid size label and price.');
        return;
    }
    
    tempVariations.push({
        label: label,
        price: price,
        is_default: tempVariations.length === 0 ? 1 : 0
    });
    
    renderTempVariations();
    
    // Clear inputs
    document.getElementById('new_var_label').value = '';
    document.getElementById('new_var_price').value = '';
    
    // Update hidden input
    document.getElementById('variations_json').value = JSON.stringify(tempVariations);
}

function removeTempVariation(index) {
    tempVariations.splice(index, 1);
    // If we removed the default, set first one as default
    if (tempVariations.length > 0 && tempVariations.findIndex(v => v.is_default === 1) === -1) {
        tempVariations[0].is_default = 1;
    }
    renderTempVariations();
    document.getElementById('variations_json').value = JSON.stringify(tempVariations);
}

function setTempDefault(index) {
    tempVariations.forEach((v, i) => {
        v.is_default = i === index ? 1 : 0;
    });
    renderTempVariations();
    document.getElementById('variations_json').value = JSON.stringify(tempVariations);
}

function renderTempVariations() {
    const list = document.getElementById('tempVariationsList');
    
    if (tempVariations.length === 0) {
        list.innerHTML = '<div class="no-variations"><i class="fas fa-tags"></i>No sizes added yet. You can add sizes later if needed.</div>';
        return;
    }
    
    list.innerHTML = tempVariations.map((v, idx) => `
        <div class="variation-item ${v.is_default == 1 ? 'is-default' : ''}">
            <span class="variation-badge ${v.is_default == 1 ? 'default' : 'regular'}">${v.is_default == 1 ? '⭐ Default' : 'Option'}</span>
            <span class="variation-label">${escHtml(v.label)}</span>
            <span class="variation-price">₱${parseFloat(v.price).toFixed(2)}</span>
            <div class="variation-actions">
                ${v.is_default != 1 ? `
                <button type="button" class="btn-set-default" onclick="setTempDefault(${idx})">⭐ Default</button>
                ` : ''}
                <button type="button" class="btn-del-variation" onclick="removeTempVariation(${idx})">🗑️</button>
            </div>
        </div>
    `).join('');
}

// Reset temp variations when modal is closed
const originalCloseModal = closeModal;
closeModal = function(id) {
    originalCloseModal(id);
    if (id === 'addModal') {
        tempVariations = [];
        renderTempVariations();
        document.getElementById('variations_json').value = '';
    }
    if (id === 'editModal') {
        editTempVariations = [];
        renderEditTempVariations();
        document.getElementById('edit_variations_json').value = '';
    }
}

// ===================== TEMP VARIATIONS IN EDIT FORM =====================
let editTempVariations = [];
let currentEditingProductId = null;

// Extend editProduct to load existing variations
function editProduct(product) {
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_description').value = product.description || '';
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_category').value = product.category;
    document.getElementById('edit_stock').value = product.stock;
    document.getElementById('edit_available').checked = product.is_available == 1;

    document.getElementById('edit_product_image').value = '';
    document.getElementById('edit_preview_container').style.display = 'none';

    // current image
    if (product.image) {
        document.getElementById('current_image_container').style.display = 'block';
        document.getElementById('current_image_preview').src = '../' + product.image;
    } else {
        document.getElementById('current_image_container').style.display = 'none';
    }

    // reset variations first
    editTempVariations = [];
    document.getElementById('editVariationsList').innerHTML =
        '<div class="no-variations"><i class="fas fa-sync fa-spin"></i> Loading sizes...</div>';

    document.getElementById('editModal').classList.add('show');

    // load variations AFTER modal opens
    fetch('?ajax=variations&product_id=' + product.id)
        .then(r => r.json())
        .then(data => {
            editTempVariations = data.map(v => ({
                id: v.id,
                label: v.variation_label,
                price: parseFloat(v.price),
                is_default: parseInt(v.is_default),
                existing: true
            }));

            renderEditTempVariations();
            document.getElementById('edit_variations_json').value =
                JSON.stringify(editTempVariations);
        })
        .catch(() => {
            editTempVariations = [];
            renderEditTempVariations();
        });
}

function addEditTempVariation() {
    const label = document.getElementById('edit_new_var_label').value.trim();
    const price = parseFloat(document.getElementById('edit_new_var_price').value);
    
    if (!label || isNaN(price) || price <= 0) {
        alert('Please enter valid size label and price.');
        return;
    }
    
    editTempVariations.push({
        label: label,
        price: price,
        is_default: editTempVariations.length === 0 ? 1 : 0,
        existing: false
    });
    
    renderEditTempVariations();
    
    // Clear inputs
    document.getElementById('edit_new_var_label').value = '';
    document.getElementById('edit_new_var_price').value = '';
    
    // Update hidden input
    document.getElementById('edit_variations_json').value = JSON.stringify(editTempVariations);
}

function removeEditTempVariation(index) {
    editTempVariations.splice(index, 1);
    // If we removed the default, set first one as default
    if (editTempVariations.length > 0 && editTempVariations.findIndex(v => v.is_default === 1) === -1) {
        editTempVariations[0].is_default = 1;
    }
    renderEditTempVariations();
    document.getElementById('edit_variations_json').value = JSON.stringify(editTempVariations);
}

function setEditTempDefault(index) {
    editTempVariations.forEach((v, i) => {
        v.is_default = i === index ? 1 : 0;
    });
    renderEditTempVariations();
    document.getElementById('edit_variations_json').value = JSON.stringify(editTempVariations);
}

function renderEditTempVariations() {
    const list = document.getElementById('editVariationsList');
    
    if (editTempVariations.length === 0) {
        list.innerHTML = '<div class="no-variations"><i class="fas fa-tags"></i>No sizes added yet. You can add sizes here.</div>';
        return;
    }
    
    list.innerHTML = editTempVariations.map((v, idx) => `
        <div class="variation-item ${v.is_default == 1 ? 'is-default' : ''}">
            <span class="variation-badge ${v.is_default == 1 ? 'default' : 'regular'}">${v.is_default == 1 ? '⭐ Default' : 'Option'}</span>
            <span class="variation-label">${escHtml(v.label)} ${v.existing ? '<span style="color:#94a3b8; font-size:11px; font-weight:normal;">(existing)</span>' : '<span style="color:#22c55e; font-size:11px; font-weight:normal;">(new)</span>'}</span>
            <span class="variation-price">₱${parseFloat(v.price).toFixed(2)}</span>
            <div class="variation-actions">
                ${v.is_default != 1 ? `
                <button type="button" class="btn-set-default" onclick="setEditTempDefault(${idx})">⭐ Default</button>
                ` : ''}
                <button type="button" class="btn-del-variation" onclick="removeEditTempVariation(${idx})">🗑️</button>
            </div>
        </div>
    `).join('');
}


</script>

<?php

?>

<!-- Image Preview Modal -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
    <img class="image-modal-content" id="modalImage" src="" alt="Product Image">
</div>

</body>
</html>