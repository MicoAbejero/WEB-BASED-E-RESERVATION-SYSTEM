<?php
session_start();
include 'includes/db.php';

$message = "";

if (isset($_GET['registered'])) {
    $success = "✓ Registration successful! Please login with your credentials.";
}

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();

        // Simple plain text password comparison
        $password_valid = ($password === $user['password']);

        if ($password_valid) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            if ($user['role'] == 'admin') {
                header("Location: admin/dashboard.php");
                exit();
            } else {
                header("Location: user/dashboard.php");
                exit();
            }
        } else {
            $message = "Incorrect password";
        }
    } else {
        $stmt->close();
        $message = "User not found";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - E-Reserve for Crochet Flowers</title>
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
        
        .login-container {
            background: white;
            padding: 48px;
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 440px;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
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
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
            position: relative;
            z-index: 1;
        }
        
        .login-icon {
            font-size: 64px;
            margin-bottom: 16px;
            display: block;
        }
        
        .login-container h1 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }
        
        .login-container .subtitle {
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
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(244, 114, 182, 0.4);
        }
        
        .register-link {
            text-align: center;
            margin-top: 24px;
            color: #64748b;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }
        
        .register-link a {
            color: #f472b6;
            text-decoration: none;
            font-weight: 700;
        }
        
        .register-link a:hover {
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
        
        .forgot-password {
            text-align: right;
            margin-top: -8px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .forgot-password a {
            color: #64748b;
            font-size: 13px;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .forgot-password a:hover {
            color: #f472b6;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: #94a3b8;
            font-size: 13px;
            position: relative;
            z-index: 1;
        }
        
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
        
        .divider span {
            padding: 0 15px;
        }
        
        .features {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-top: 24px;
            position: relative;
            z-index: 1;
        }
        
        .feature {
            text-align: center;
            color: #64748b;
            font-size: 12px;
        }
        
        .feature i {
            font-size: 20px;
            color: #f472b6;
            margin-bottom: 4px;
            display: block;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-header">
        <span class="login-icon">🌸</span>
        <h1>Login</h1>
        <p class="subtitle">Welcome back! Login to your account</p>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="error">❌ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>Email Address</label>
            <div class="input-wrapper">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" required placeholder="Enter your email">
            </div>
        </div>
        
        <div class="form-group">
            <label>Password</label>
            <div class="input-wrapper">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" required placeholder="Enter your password">
            </div>
        </div>
        
        <div class="forgot-password">
            <a href="forgot_password.php">Forgot password?</a>
        </div>
        
        <button type="submit" name="login" class="btn-login">
            <i class="fas fa-sign-in-alt"></i> Login
        </button>
    </form>
    
    <p class="register-link">
        Don't have an account? <a href="register.php">Register here</a>
    </p>
    
    <div class="features">
        <div class="feature">
            <i class="fas fa-lock"></i>
            Secure
        </div>
        <div class="feature">
            <i class="fas fa-bolt"></i>
            Fast
        </div>
        <div class="feature">
            <i class="fas fa-heart"></i>
            Easy
        </div>
    </div>
</div>

</body>
</html>
