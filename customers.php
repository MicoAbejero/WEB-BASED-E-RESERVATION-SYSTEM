<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

require_permission('customer.manage', '../login.php');

$message = "";
$success = "";

// Handle customer actions
if (isset($_POST['delete_customer'])) {
    $customer_id = (int)$_POST['customer_id'];
    // Don't allow deleting self
    if ($customer_id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id = $customer_id AND role = 'customer'");
        $success = "✓ Customer deleted successfully!";
    } else {
        $message = "You cannot delete your own account!";
    }
}

if (isset($_POST['reset_password'])) {
    $customer_id = (int)$_POST['customer_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords match
    if ($new_password !== $confirm_password) {
        $message = "⚠️ Passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $message = "⚠️ Password must be at least 6 characters!";
    } else {
        // Store as plain text (not hashed)
        $conn->query("UPDATE users SET password = '$new_password' WHERE id = $customer_id");
        $success = "✓ Customer password has been reset to: " . htmlspecialchars($new_password);
    }
}

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = "role = 'customer'";
if ($search) {
    $search_escaped = $conn->real_escape_string($search);
    $where .= " AND (name LIKE '%$search_escaped%' OR email LIKE '%$search_escaped%' OR phone LIKE '%$search_escaped%')";
}

$customers = $conn->query("SELECT * FROM users WHERE $where ORDER BY created_at DESC");

// Stats
$total_customers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'")->fetch_assoc()['total'];
$customers_this_month = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customers - E-Reserve Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        
        .customers-table {
            width: 100%;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.6);
        }
        
        .customers-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .customers-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 16px 14px;
            text-align: left;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .customers-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #334155;
        }
        
        .customers-table tbody tr:hover {
            background: linear-gradient(90deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .customer-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .customer-info .name {
            font-weight: 700;
            color: #1e293b;
        }
        
        .customer-info .email {
            color: #64748b;
            font-size: 12px;
        }
        
        .action-btns {
            display: flex;
            gap: 8px;
        }
        
        .btn-reset {
            padding: 8px 16px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        
        .btn-delete {
            padding: 8px 16px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 99999;
            backdrop-filter: blur(4px);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 28px;
            border-radius: 20px;
            width: 90%;
            max-width: 420px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
        }
        
        .modal-content h2 {
            margin: 0 0 16px 0;
            color: #1e293b;
        }
        
        .modal-content p {
            color: #64748b;
            margin-bottom: 20px;
        }
        
        .modal-content input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            margin-bottom: 20px;
        }
        
        .modal-content input:focus {
            outline: none;
            border-color: #6366f1;
        }
        
        .modal-btns {
            display: flex;
            gap: 12px;
        }
        
        .modal-btns button {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
        }
        
        .btn-cancel {
            background: #f1f5f9;
            color: #64748b;
            border: none;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🌸 Crochet Admin</h2>
        <a href="dashboard.php"><i class="far fa-chart-bar"></i> Dashboard</a>
        <a href="customers.php" class="active"><i class="fas fa-users"></i> Customers</a>
        <a href="products.php"><i class="far fa-gem"></i> Products</a>
        <a href="reservations.php"><i class="fas fa-clipboard-list"></i> Reservations</a>
        <a href="pickup_calendar.php"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">

        <div class="topbar">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</h1>
            <div class="live-indicator">
                <span class="dot"></span>
                Live
            </div>
        </div>

        <?php if ($success): ?>
            <div class="success-message" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; padding: 18px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2);">
                <span style="font-size: 24px;">🎉</span>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="error-message" style="background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; padding: 18px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500;">
                <span style="font-size: 24px;">❌</span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2>👥 Customer Management</h2>
            <p>View and manage all registered customers</p>
        </div>

        <div class="cards">
            <div class="card stat-total">
                <div class="card-icon">👥</div>
                <h3>Total Customers</h3>
                <p><?php echo $total_customers; ?></p>
            </div>
            <div class="card stat-confirmed">
                <div class="card-icon">📅</div>
                <h3>New This Month</h3>
                <p><?php echo $customers_this_month; ?></p>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter-container">
            <form method="GET" class="search-form" id="searchForm">
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="searchInput" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn-search">🔍 Search</button>
                <a href="customers.php" class="btn-clear">Clear</a>
            </form>
        </div>

        <div class="customers-table">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Password</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers->num_rows > 0): ?>
                        <?php while ($customer = $customers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="customer-info">
                                        <div class="customer-avatar">
                                            <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="name"><?php echo htmlspecialchars($customer['name']); ?></div>
                                            <div class="email"><?php echo htmlspecialchars($customer['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($customer['phone'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($customer['password']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-reset" onclick="openResetModal(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name']); ?>')">Reset Password</button>
                                        <?php if ($customer['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" onsubmit="return confirmDelete('<?php echo htmlspecialchars($customer['name']); ?>')" style="display:inline;">
                                                <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                                <button type="submit" name="delete_customer" class="btn-delete">🗑️ Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: #64748b;">
                                No customers found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="modal">
    <div class="modal-content">
        <h2>🔑 Reset Customer Password</h2>
        <p>Enter a new password for <strong id="customerName"></strong></p>
        <form method="POST" id="resetForm" onsubmit="return confirmReset()">
            <input type="hidden" name="customer_id" id="resetCustomerId">
            <input type="password" name="new_password" id="newPassword" placeholder="Enter new password" required minlength="6">
            <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm new password" required minlength="6" style="margin-bottom: 12px;">
            <div class="modal-btns">
                <button type="submit" name="reset_password" class="btn-submit">✅ Confirm Reset</button>
                <button type="button" class="btn-cancel" onclick="closeResetModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Live search without page refresh
const customerSearchInput = document.getElementById('searchInput');
const customerRows = Array.from(document.querySelectorAll('.customers-table tbody tr'));

if (customerSearchInput) {
    customerSearchInput.addEventListener('input', function() {
        const term = this.value.trim().toLowerCase();
        let visibleCount = 0;

        customerRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const match = term === '' || text.includes(term);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        let liveNoResult = document.getElementById('live-no-customer-row');
        if (visibleCount === 0 && customerRows.length > 0) {
            if (!liveNoResult) {
                liveNoResult = document.createElement('tr');
                liveNoResult.id = 'live-no-customer-row';
                liveNoResult.innerHTML = '<td colspan="5" style="text-align:center; padding: 30px; color:#64748b;">No matching customers.</td>';
                document.querySelector('.customers-table tbody').appendChild(liveNoResult);
            }
        } else if (liveNoResult) {
            liveNoResult.remove();
        }
    });
}

function openResetModal(customerId, customerName) {
    document.getElementById('resetCustomerId').value = customerId;
    document.getElementById('customerName').textContent = customerName;
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    document.getElementById('resetModal').classList.add('show');
}

function closeResetModal() {
    document.getElementById('resetModal').classList.remove('show');
}

// Confirm reset password with verification
function confirmReset() {
    const password = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (!password || password.length < 6) {
        alert('⚠️ Password must be at least 6 characters');
        return false;
    }
    
    if (password !== confirmPassword) {
        alert('⚠️ Passwords do not match! Please re-enter.');
        return false;
    }
    
    return confirm('⚠️ Are you sure you want to reset this customer\'s password?\n\nNew password: ' + password + '\n\nThis action cannot be undone!');
}

// Confirm delete customer
function confirmDelete(customerName) {
    return confirm('⚠️ WARNING: You are about to delete customer "' + customerName + '".\n\nThis will also delete ALL their reservations!\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}
</script>

<script src="../assets/js/script.js"></script>
</body>
</html>
