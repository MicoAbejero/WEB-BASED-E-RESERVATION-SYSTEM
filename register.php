<?php
session_start();
include 'includes/db.php';

$message = "";
$success = "";

if (isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $message = "Please fill in all required fields";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters";
    } else {
        // Use prepared statement to prevent SQL injection
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check_result = $check->get_result();
        
        if ($check_result->num_rows > 0) {
            $check->close();
            $message = "Email already registered";
        } else {
            $check->close();
            
            // Store password as plain text
            $plain_password = $password;
            
            // Insert user with plain text password
            $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')");
            $stmt->bind_param("ssss", $name, $email, $phone, $plain_password);
            
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: login.php?registered=1");
                exit();
            } else {
                $stmt->close();
                $message = "Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - E-Reserve for Crochet Flowers</title>
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
        
        .register-container {
            background: white;
            padding: 28px;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 420px;
            position: relative;
            overflow: hidden;
        }
        
        .register-container::before {
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
        
        .register-header {
            text-align: center;
            margin-bottom: 28px;
            position: relative;
            z-index: 1;
        }
        
        .register-icon {
            font-size: 56px;
            margin-bottom: 12px;
            display: block;
        }
        
        .register-container h1 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 26px;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }
        
        .register-container .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 0;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 18px;
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
        
        .password-requirements {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
        }
        
        .btn-register {
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
        
        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(244, 114, 182, 0.4);
        }
        
        .login-link {
            text-align: center;
            margin-top: 24px;
            color: #64748b;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }
        
        .login-link a {
            color: #f472b6;
            text-decoration: none;
            font-weight: 700;
        }
        
        .login-link a:hover {
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
        
        .benefits {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            position: relative;
            z-index: 1;
        }
        
        .benefits h4 {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 16px;
        }
        
        .benefit-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .benefit {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #64748b;
        }
        
        .benefit i {
            color: #22c55e;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="register-header">
        <span class="register-icon">🌸</span>
        <h1>Create Account</h1>
        <p class="subtitle">Join our crochet flower community</p>
    </div>
    
    <?php if ($message): ?>
        <div class="error">❌ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>Full Name *</label>
            <div class="input-wrapper">
                <i class="fas fa-user"></i>
                <input type="text" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" placeholder="Enter your full name">
            </div>
        </div>
        
        <div class="form-group">
            <label>Email Address *</label>
            <div class="input-wrapper">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="Enter your email">
            </div>
        </div>
        
        <div class="form-group">
            <label>Phone Number</label>
            <div class="input-wrapper">
                <i class="fas fa-phone"></i>
                <input type="tel" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" placeholder="Enter your phone number">
            </div>
        </div>
        
        <div class="form-group">
            <label>Password *</label>
            <div class="input-wrapper">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" required placeholder="Create a password">
            </div>
            <p class="password-requirements">At least 6 characters</p>
        </div>
        
        <div class="form-group">
            <label>Confirm Password *</label>
            <div class="input-wrapper">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm_password" required placeholder="Confirm your password">
            </div>
        </div>
        
        <button type="submit" name="register" class="btn-register">
            <i class="fas fa-user-plus"></i> Create Account
        </button>
    </form>
    
    <p class="login-link">
        Already have an account? <a href="login.php">Login here</a>
    </p>
    
    <div class="benefits">
        <h4>✨ Benefits of joining</h4>
        <div class="benefit-list">
            <div class="benefit">
                <i class="fas fa-check"></i>
                <span>Easy ordering</span>
            </div>
            <div class="benefit">
                <i class="fas fa-check"></i>
                <span>Order tracking</span>
            </div>
            <div class="benefit">
                <i class="fas fa-check"></i>
                <span>Exclusive deals</span>
            </div>
            <div class="benefit">
                <i class="fas fa-check"></i>
                <span>Quick checkout</span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
