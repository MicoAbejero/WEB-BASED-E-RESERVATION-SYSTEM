<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$success = "";

// Get current user data
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    if (empty($name)) {
        $message = "Name is required";
    } else {
        $conn->query("UPDATE users SET name = '$name', phone = '$phone', address = '$address' WHERE id = $user_id");
        $_SESSION['name'] = $name;
        $success = "✓ Profile updated successfully!";
        $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password (plain text)
    $password_valid = ($current_password === $user['password']);
    
    if (!$password_valid) {
        $message = "Current password is incorrect";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters";
    } else {
        // Store as plain text
        $conn->query("UPDATE users SET password = '$new_password' WHERE id = $user_id");
        $success = "✓ Password changed successfully!";
        $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile - E-Reserve for Crochet Flowers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: white;
            padding: 32px;
            border-radius: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.6);
            display: flex;
            align-items: center;
            gap: 24px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: 700;
        }
        
        .profile-header h2 {
            margin: 0 0 8px 0;
            color: #1e293b;
            font-size: 24px;
        }
        
        .profile-header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }
        
        .profile-section {
            background: white;
            padding: 32px;
            border-radius: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.6);
        }
        
        .profile-section h3 {
            margin: 0 0 24px 0;
            color: #1e293b;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-section h3 i {
            color: #f472b6;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f472b6;
            box-shadow: 0 0 0 4px rgba(244, 114, 182, 0.15);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn-save {
            padding: 14px 32px;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3);
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 114, 182, 0.4);
        }
        
        .success-message {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #15803d;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .error-message {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .account-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .info-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .info-card label {
            display: block;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .info-card p {
            margin: 0;
            color: #1e293b;
            font-weight: 600;
            font-size: 15px;
        }
        
        .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 24px 0;
        }
        
        @media (max-width: 600px) {
            .form-row, .account-info {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🌸 Crochet</h2>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="products.php"><i class="far fa-gem"></i> Products</a>
        <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
        <a href="reservations.php"><i class="fas fa-clipboard-list"></i> My Reservations</a>
        <a href="pickup_calendar.php"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php" class="active"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">

        <div class="topbar">
            <h1>👤 My Profile</h1>
        </div>

        <div class="profile-container">
            
            <?php if ($message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                    <p>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>

            <!-- Account Info -->
            <div class="profile-section">
                <h3><i class="fas fa-user-circle"></i> Account Information</h3>
                <div class="account-info">
                    <div class="info-card">
                        <label>Email Address</label>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="info-card">
                        <label>Account Type</label>
                        <p><?php echo ucfirst($user['role']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="profile-section">
                <h3><i class="fas fa-id-card"></i> Personal Information</h3>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" required value="<?php echo htmlspecialchars($user['name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Enter phone number">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" placeholder="Enter your address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="update_profile" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="profile-section">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Current Password *</label>
                        <input type="password" name="current_password" required placeholder="Enter current password">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>New Password *</label>
                            <input type="password" name="new_password" required placeholder="Enter new password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password *</label>
                            <input type="password" name="confirm_password" required placeholder="Confirm new password">
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn-save">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>

        </div>

    </div>

</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
