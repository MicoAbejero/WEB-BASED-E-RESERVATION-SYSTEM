<?php
header('Content-Type: application/json');
include 'includes/db.php';
include 'includes/auth.php';

$response = [
    'reservations' => [],
    'stats' => [],
    'timestamp' => time()
];

$action = $_GET['action'] ?? '';

switch ($action) {
    // Sidebar badge counts for admin/user
    case 'get_sidebar_counts':
        $response = [
            'sidebar' => [
                'pending_reservations' => 0,
                'cart_items' => 0,
                'today_pickups' => 0
            ],
            'timestamp' => time()
        ];

        $role = $_SESSION['role'] ?? null;

        if ($role === 'admin') {
            if (has_permission('dashboard.admin.view')) {
                $response['sidebar']['pending_reservations'] = (int)($conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'")->fetch_assoc()['total'] ?? 0);
                $response['sidebar']['today_pickups'] = (int)($conn->query("SELECT COUNT(*) as total FROM reservations WHERE status IN ('pending', 'confirmed') AND COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY)) = CURDATE()")->fetch_assoc()['total'] ?? 0);
            }
        } elseif ($role === 'customer') {
            if (has_permission('reservation.view_own')) {
                $user_id = (int)($_SESSION['user_id'] ?? 0);

                $response['sidebar']['pending_reservations'] = (int)($conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id AND status = 'pending'")->fetch_assoc()['total'] ?? 0);
                $response['sidebar']['cart_items'] = (int)($conn->query("SELECT COALESCE(SUM(quantity),0) as total FROM cart WHERE user_id = $user_id")->fetch_assoc()['total'] ?? 0);
                $response['sidebar']['today_pickups'] = (int)($conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id AND status IN ('pending', 'confirmed') AND COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY)) = CURDATE()")->fetch_assoc()['total'] ?? 0);
            }
        }

        echo json_encode($response);
        exit();

    // Get reservation data for admin
    case 'get_reservations':
        if (!has_permission('reservation.manage')) {
            echo json_encode(['error' => 'Access denied']);
            exit();
        }
        
        $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
        
        // Get new reservations
        $reservations = $conn->query("SELECT r.*, u.name as customer_name, u.email as customer_email, 
                                     COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name 
                                     FROM reservations r 
                                     JOIN users u ON r.user_id = u.id 
                                     LEFT JOIN products p ON r.product_id = p.id 
                                     WHERE r.id > $last_id
                                     ORDER BY r.created_at DESC LIMIT 20");
        
        $new_reservations = [];
        $max_id = $last_id;
        while ($res = $reservations->fetch_assoc()) {
            $new_reservations[] = $res;
            $max_id = max($max_id, $res['id']);
        }
        
        // Get updated reservations (status changes)
        $since = isset($_GET['since']) ? (int)$_GET['since'] : time() - 300;
        $updated = $conn->query("SELECT r.*, u.name as customer_name, u.email as customer_email, 
                                COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name 
                                FROM reservations r 
                                JOIN users u ON r.user_id = u.id 
                                LEFT JOIN products p ON r.product_id = p.id 
                                WHERE UNIX_TIMESTAMP(r.created_at) >= $since
                                ORDER BY r.created_at DESC");
        
        $updated_reservations = [];
        while ($res = $updated->fetch_assoc()) {
            $updated_reservations[] = $res;
        }
        
        // Get stats
        $stats = [
            'total' => $conn->query("SELECT COUNT(*) as total FROM reservations")->fetch_assoc()['total'],
            'pending' => $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'")->fetch_assoc()['total'],
            'confirmed' => $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'confirmed'")->fetch_assoc()['total'],
            'completed' => $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'completed'")->fetch_assoc()['total'],
            'cancelled' => $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'cancelled'")->fetch_assoc()['total']
        ];
        
        $response = [
            'new_reservations' => $new_reservations,
            'updated_reservations' => $updated_reservations,
            'max_id' => $max_id,
            'stats' => $stats,
            'timestamp' => time()
        ];
        break;
    
    // Get reservation data for user
    case 'get_user_reservations':
        if (!has_permission('reservation.view_own')) {
            echo json_encode(['error' => 'Access denied']);
            exit();
        }
        
        $user_id = $_SESSION['user_id'];
        $since = isset($_GET['since']) ? (int)$_GET['since'] : time() - 300;
        
        // Get user's reservations
        $reservations = $conn->query("SELECT r.*, COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name 
                                     FROM reservations r 
                                     LEFT JOIN products p ON r.product_id = p.id 
                                     WHERE r.user_id = $user_id 
                                     ORDER BY r.created_at DESC");
        
        $res_data = [];
        while ($res = $reservations->fetch_assoc()) {
            $res_data[] = $res;
        }
        
        // Get stats
        $stats = [
            'total' => $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id")->fetch_assoc()['total'],
            'pending' => $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id AND status = 'pending'")->fetch_assoc()['total'],
            'completed' => $conn->query("SELECT COUNT(*) as total FROM reservations WHERE user_id = $user_id AND status = 'completed'")->fetch_assoc()['total']
        ];
        
        $response = [
            'reservations' => $res_data,
            'stats' => $stats,
            'timestamp' => time()
        ];
        break;
    
    // Get admin dashboard stats
    case 'get_stats':
        if (!has_permission('dashboard.admin.view')) {
            echo json_encode(['error' => 'Access denied']);
            exit();
        }
        
        $stats = [
            'total_users' => $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'],
            'total_products' => $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'],
            'total_reservations' => $conn->query("SELECT COUNT(*) as total FROM reservations")->fetch_assoc()['total'],
            'pending' => $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'")->fetch_assoc()['total']
        ];
        
        $response = [
            'stats' => $stats,
            'timestamp' => time()
        ];
        break;
    
    default:
        $response = ['error' => 'Invalid action'];
}

echo json_encode($response);
exit();
