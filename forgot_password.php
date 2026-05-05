<?php
session_start();
include 'includes/db.php';

$message = "";
$success = "";
$step = isset($_GET['step']) ? $_GET['step'] : 1;

// Step 1: Request password reset
if (isset($_POST['request_reset']) && $step == 1) {
    $email = trim($_POST['email']);
    
    // Check if email exists
    $check = $conn->query("SELECT id, name FROM users WHERE email = '$email'");
    
    if ($check->num_rows > 0) {
        $user = $check->fetch_assoc();
        
        // Generate reset token (simple random string)
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token in database (need to add reset_token and reset_expires columns)
        $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL");
        $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL");
        
        $conn->query("UPDATE users SET reset_token = '$token', reset_expires = '$expires' WHERE id = {$user['id']}");
        
        // In production, send email with reset link
        // For now, show the token for testing (remove in production)
        $success = "Password reset link generated! (For demo: <a href='forgot_password.php?step=2&token=$token'>Click here to reset</a>)";
        $step = 3; // Show success without email
    } else {
        $message = "Email not found in our system";
    }
}

// Step 2: Reset password with token
if (isset($_POST['reset_password']) && $step == 2) {
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $message = "Passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters";
    } else {
        // Check token
        $check = $conn->query("SELECT id FROM users WHERE reset_token = '$token' AND reset_expires > NOW()");
        
        if ($check->num_rows > 0) {
            $user = $check->fetch_assoc();
            
            // Store password as plain text
            $conn->query("UPDATE users SET password = '$new_password', reset_token = NULL, reset_expires = NULL WHERE id = {$user['id']}");
            
            $success = "✓ Password reset successful! <a href='login.php'>Click here to login</a>";
            $step = 3;
        } else {
            $message = "Invalid or expired reset token";
        }
    }
}

// Get token from URL for step 2
$token = isset($_GET['token']) ? $_GET['token'] : '';
if ($token && $step == 1) {
    $step = 2;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - E-Reserve for Crochet Flowers</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f472b6 100%);
            padding: 20px;
        }
        
        .forgot-container {
            background: white;
            padding: 48px;
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 440px;
            position: relative;
            overflow: hidden;
        }
        
        .forgot-container::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(244, 114, 182, 0.1) 0%, transparent 60%);
            animation: shimmer 15s linear infinite;
        }
        
        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .forgot-header {
            text-align: center;
            margin-bottom: 32px;
            position: relative;
            z-index: 1;
        }
        
        .forgot-icon {
            font-size: 64px;
            margin-bottom: 16px;
            display: block;
        }
        
        .forgot-container h1 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }
        
        .forgot-container .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 0;
            font-size: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 600;
            font-size: 14px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
        }
        
        .form-group input {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #f472b6;
            box-shadow: 0 0 0 4px rgba(244, 114, 182, 0.15);
        }
        
        .btn-reset {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3);
            position: relative;
            z-index: 1;
        }
        
        .btn-reset:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(244, 114, 182, 0.4);
        }
        
        .back-link {
            text-align: center;
            margin-top: 24px;
            color: #64748b;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }
        
        .back-link a {
            color: #f472b6;
            text-decoration: none;
            font-weight: 700;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .success {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #15803d;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .success a {
            color: #f472b6;
            font-weight: 700;
        }
    </style>
</head>
<body>

<div class="forgot-container">
    <div class="forgot-header">
        <span class="forgot-icon">🔐</span>
        <h1>Reset Password</h1>
        <p class="subtitle"><?php echo $step == 1 ? 'Enter your email to receive reset instructions' : 'Create a new password'; ?></p>
    </div>
    
    <?php if ($message): ?>
        <div class="error">❌ <?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($step == 1): ?>
        <form method="POST" action="?step=1">
            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" required placeholder="Enter your email">
                </div>
            </div>
            
            <button type="submit" name="request_reset" class="btn-reset">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>
    <?php elseif ($step == 2): ?>
        <form method="POST" action="?step=2">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label>New Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="new_password" required placeholder="Enter new password">
                </div>
            </div>
            
            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" required placeholder="Confirm new password">
                </div>
            </div>
            
            <button type="submit" name="reset_password" class="btn-reset">
                <i class="fas fa-key"></i> Reset Password
            </button>
        </form>
    <?php endif; ?>
    
    <p class="back-link">
        Remember your password? <a href="login.php">Back to Login</a>
    </p>
</div>

</body>
</html>
