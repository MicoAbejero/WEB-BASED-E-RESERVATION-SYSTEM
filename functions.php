<?php
/**
 * Security and Utility Functions
 */

// Include authentication system (RBAC)
require_once __DIR__ . '/auth.php';

// Generate CSRF token
function generate_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize input
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

// Validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number (Philippines format)
function validate_phone($phone) {
    // Allow formats: 09123456789, +639123456789, (0912) 345 6789
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 12;
}

// Generate random string
function generate_random_string($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

// Format date
function format_date($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

// Format currency
function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

// Get client IP address
function get_client_ip() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    return $ip;
}

// Log activity
function log_activity($user_id, $action, $details = '') {
    global $conn;
    
    if (!isset($conn)) {
        include 'db.php';
    }
    
    $ip = get_client_ip();
    $action = $conn->real_escape_string($action);
    $details = $conn->real_escape_string($details);
    
    $conn->query("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES ($user_id, '$action', '$details', '$ip')");
}

// Check if user is logged in - alias for backwards compatibility
function is_logged_in() {
    return is_authenticated();
}


// Redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

// Redirect if not admin
function require_admin() {
    require_login();
    
    if (!has_role('admin')) {
        header("Location: ../user/dashboard.php");
        exit();
    }
}

// Generate breadcrumb
function generate_breadcrumb($items) {
    $html = '<nav class="breadcrumb">';
    $html .= '<a href="index.php">Home</a>';
    
    foreach ($items as $label => $link) {
        if ($link) {
            $html .= ' <span class="separator">›</span> ';
            $html .= '<a href="' . $link . '">' . $label . '</a>';
        } else {
            $html .= ' <span class="separator">›</span> ';
            $html .= '<span class="current">' . $label . '</span>';
        }
    }
    
    $html .= '</nav>';
    
    return $html;
}

// Show toast notification
function show_toast($message, $type = 'info') {
    $_SESSION['toast'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Get toast and clear
function get_toast() {
    if (isset($_SESSION['toast'])) {
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
        return $toast;
    }
    return null;
}
?>
