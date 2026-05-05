<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

require_permission('pickup.manage', '../login.php');

$message = "";
$success = "";

// Create settings table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Insert default setting if not exists
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES 
('max_reservations_per_day', '5', 'Maximum number of reservations allowed per pickup day')");

// Create date_settings table if it doesn't exist (for per-date limits)
$conn->query("CREATE TABLE IF NOT EXISTS date_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_date DATE UNIQUE NOT NULL,
    max_reservations INT DEFAULT 5,
    notes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Get all reservations for calendar view
// NOTE: Force effective_pickup_date to DATE so JS keys always match YYYY-MM-DD.
$allReservations = $conn->query("SELECT r.*, u.name as customer_name, u.email as customer_email, 
                                 COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name,
                                 DATE(COALESCE(NULLIF(r.pickup_date, '0000-00-00'), DATE_ADD(r.reservation_date, INTERVAL 3 DAY))) as effective_pickup_date
                                 FROM reservations r 
                                 LEFT JOIN users u ON r.user_id = u.id 
                                 LEFT JOIN products p ON r.product_id = p.id 
                                 WHERE r.status IN ('pending', 'confirmed')
                                 ORDER BY effective_pickup_date ASC");

// Group by pickup date
$reservationsByDate = [];
while ($row = $allReservations->fetch_assoc()) {
    $date = !empty($row['effective_pickup_date']) ? date('Y-m-d', strtotime($row['effective_pickup_date'])) : null;
    if (empty($date)) {
        continue;
    }
    if (!isset($reservationsByDate[$date])) {
        $reservationsByDate[$date] = [];
    }
    $reservationsByDate[$date][] = $row;
}

// Get all date-specific settings
$dateSettings = [];
$settingsResult = $conn->query("SELECT pickup_date, max_reservations FROM date_settings");
while ($row = $settingsResult->fetch_assoc()) {
    $dateSettings[$row['pickup_date']] = $row['max_reservations'];
}

// Get stats
$today = date('Y-m-d');
$total_today = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE DATE(COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY))) = '$today' AND status IN ('pending', 'confirmed')")->fetch_assoc()['total'];
$default_max = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'max_reservations_per_day'")->fetch_assoc()['setting_value'] ?? 5;
$total_pending = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'")->fetch_assoc()['total'];
$total_confirmed = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'confirmed'")->fetch_assoc()['total'];

// Lightweight JSON endpoint so the calendar can auto-refresh while page is open
if (isset($_GET['ajax']) && $_GET['ajax'] === 'calendar_data') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'reservationsByDate' => $reservationsByDate,
        'dateSettings' => $dateSettings,
        'defaultMax' => (int)$default_max,
        'stats' => [
            'today' => (int)$total_today,
            'pending' => (int)$total_pending,
            'confirmed' => (int)$total_confirmed
        ]
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pickup Calendar - E-Reserve Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .calendar-container {
            background: white;
            border-radius: 20px;
            padding: 28px;
            margin-top: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(226, 232, 240, 0.6);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .calendar-header h3 {
            margin: 0;
            font-size: 30px;
            color: #1e293b;
        }
        
        .calendar-nav {
            display: flex;
            gap: 12px;
        }
        
        .calendar-nav button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .calendar-nav button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3);
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 24px;
        }
        
        .calendar-day-header {
            text-align: center;
            padding: 12px;
            font-weight: 700;
            color: #64748b;
            font-size: 16px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #f8fafc;
            border: 2px solid transparent;
            min-height: 90px;
        }
        
        .calendar-day:hover {
            background: linear-gradient(135deg, #fdf2f8 0%, #faf5ff 100%);
            border-color: #f472b6;
            transform: scale(1.02);
        }
        
        .calendar-day.other-month {
            opacity: 0.4;
        }
        
        .calendar-day.today {
            background: linear-gradient(135deg, #f472b6, #c084fc);
            color: white;
        }
        
        .calendar-day.has-reservations {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            border-color: #3b82f6;
        }
        
        .calendar-day.has-reservations.full {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-color: #ef4444;
        }
        
        .calendar-day.has-reservations.near-full {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-color: #f59e0b;
        }
        
        .calendar-day.has-reservations:hover {
            transform: scale(1.05);
        }
        
        .calendar-day-number {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .calendar-day-count {
            font-size: 14px;
            font-weight: 600;
        }
        
        .calendar-day.today .calendar-day-count {
            color: white;
        }
        
        .calendar-day.has-reservations .calendar-day-count {
            color: #1e40af;
        }
        
        .calendar-day.has-reservations.full .calendar-day-count {
            color: #dc2626;
        }
        
        .calendar-day.has-reservations.near-full .calendar-day-count {
            color: #92400e;
        }
        
        .legend {
            display: flex;
            gap: 24px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            color: #64748b;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 6px;
        }
        
        .legend-today {
            background: linear-gradient(135deg, #f472b6, #c084fc);
        }
        
        .legend-reservations {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            border: 2px solid #3b82f6;
        }
        
        .legend-near-full {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
        }
        
        .legend-full {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 2px solid #ef4444;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 28px;
            max-width: 650px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #1e293b;
            font-size: 20px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #64748b;
            transition: color 0.2s;
        }
        
        .modal-close:hover {
            color: #ef4444;
        }
        
        .date-info-box {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #f59e0b;
        }
        
        .date-info-box h4 {
            margin: 0 0 12px 0;
            color: #92400e;
            font-size: 16px;
        }
        
        .date-stats {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        
        .date-stat {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .date-stat .icon {
            font-size: 24px;
        }
        
        .date-stat .label {
            color: #92400e;
            font-size: 12px;
        }
        
        .date-stat .value {
            color: #78350f;
            font-size: 24px;
            font-weight: 700;
        }
        
        .limit-settings {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
        }
        
        .limit-settings h4 {
            margin: 0 0 16px 0;
            color: #1e293b;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .limit-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .limit-form label {
            color: #64748b;
            font-size: 14px;
        }
        
        .limit-form input {
            padding: 10px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            width: 80px;
            text-align: center;
        }
        
        .limit-form input:focus {
            outline: none;
            border-color: #f472b6;
        }
        
        .limit-form button {
            padding: 10px 20px;
            background: linear-gradient(135deg, #f472b6, #c084fc);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .limit-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3);
        }
        
        .reservations-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
        }
        
        .reservations-section h4 {
            margin: 0 0 16px 0;
            color: #1e293b;
            font-size: 16px;
        }
        
        .reservation-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            background: white;
            border-radius: 12px;
            margin-bottom: 12px;
            align-items: center;
            border-left: 4px solid #3b82f6;
        }
        
        .reservation-item:last-child {
            margin-bottom: 0;
        }
        
        .reservation-item.pending {
            border-left-color: #f59e0b;
        }
        
        .reservation-item.confirmed {
            border-left-color: #22c55e;
        }
        
        .reservation-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #fbcfe8 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .reservation-info {
            flex: 1;
        }
        
        .reservation-info h5 {
            margin: 0 0 4px 0;
            color: #1e293b;
            font-size: 15px;
        }
        
        .reservation-info p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }
        
        .reservation-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-confirmed {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .no-reservations {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        
        .no-reservations .icon {
            font-size: 48px;
            margin-bottom: 12px;
        }
        
        .stats-row {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .stat-box .icon {
            font-size: 28px;
        }
        
        .stat-box .info h5 {
            margin: 0;
            color: #64748b;
            font-size: 12px;
        }
        
        .stat-box .info p {
            margin: 0;
            color: #1e293b;
            font-size: 24px;
            font-weight: 700;
        }
        
        .stat-box.warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🌸 Crochet Admin</h2>
        <a href="dashboard.php"><i class="far fa-chart-bar"></i> Dashboard</a>
        <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
        <a href="products.php"><i class="far fa-gem"></i> Products</a>
        <a href="reservations.php"><i class="fas fa-clipboard-list"></i> Reservations</a>
        <a href="pickup_calendar.php" class="active"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">

        <div class="topbar">
            <h1>📅 Pickup Calendar</h1>
        </div>

        <div class="page-header">
            <h2>Manage Pickup Schedule</h2>
            <p>Click on a date to view reservations and adjust limits</p>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box warning">
                <span class="icon">📅</span>
                <div class="info">
                    <h5>Today</h5>
                    <p id="statToday"><?php echo $total_today; ?> / <?php echo $default_max; ?></p>
                </div>
            </div>
            <div class="stat-box">
                <span class="icon">⏳</span>
                <div class="info">
                    <h5>Pending</h5>
                    <p id="statPending"><?php echo $total_pending; ?></p>
                </div>
            </div>
            <div class="stat-box">
                <span class="icon">✅</span>
                <div class="info">
                    <h5>Confirmed</h5>
                    <p id="statConfirmed"><?php echo $total_confirmed; ?></p>
                </div>
            </div>
        </div>

        <div class="calendar-container">
            <div class="calendar-header">
                <h3 id="currentMonth">March 2026</h3>
                <div class="calendar-nav">
                    <button onclick="changeMonth(-1)">← Previous</button>
                    <button onclick="changeMonth(1)">Next →</button>
                    <button onclick="goToToday()" style="background: #64748b;">Today</button>
                </div>
            </div>
            
            <div class="calendar-grid" id="calendarGrid">
                <div class="calendar-day-header">Sun</div>
                <div class="calendar-day-header">Mon</div>
                <div class="calendar-day-header">Tue</div>
                <div class="calendar-day-header">Wed</div>
                <div class="calendar-day-header">Thu</div>
                <div class="calendar-day-header">Fri</div>
                <div class="calendar-day-header">Sat</div>
            </div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color legend-today"></div>
                    <span>Today</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-reservations"></div>
                    <span>Has Reservations</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-near-full"></div>
                    <span>Nearly Full (4/5)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-full"></div>
                    <span>Fully Booked</span>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Modal for date details -->
<div class="modal" id="dateModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">📅 Date Details</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        
        <!-- Date Info Box -->
        <div class="date-info-box">
            <h4 id="selectedDateTitle">📅 March 20, 2026</h4>
            <div class="date-stats" id="dateStats">
                <!-- Filled by JavaScript -->
            </div>
        </div>
        
        <!-- Limit Settings for this specific date -->
        <div class="limit-settings">
            <h4>⚙️ Set Reservation Limit for This Date</h4>
            <form class="limit-form" id="limitForm">
                <input type="hidden" name="update_date_limit" value="1">
                <input type="hidden" name="pickup_date" id="limitDateInput" value="">
                <label>Maximum reservations:</label>
                <input type="number" name="max_reservations" id="limitInput" value="5" min="0" max="50">
                <button type="submit" id="updateBtn">Update Limit</button>
            </form>
            <p style="margin: 12px 0 0 0; color: #64748b; font-size: 13px;">
                💡 Different dates can have different limits
            </p>
            <div id="updateMessage" style="display:none; margin-top: 12px; padding: 12px; border-radius: 8px; background: #dcfce7; color: #15803d; font-weight: 600;"></div>
        </div>
        
        <!-- Reservations List -->
        <div class="reservations-section">
            <h4>📦 Reservations for This Date</h4>
            <div id="modalReservations">
                <!-- Filled by JavaScript -->
            </div>
        </div>
    </div>
</div>

<?php
// Handle date-specific limit update
if (isset($_POST['update_date_limit'])) {
    $pickup_date = $_POST['pickup_date'];
    $max_reservations = (int)$_POST['max_reservations'];
    
    $conn->query("INSERT INTO date_settings (pickup_date, max_reservations) 
                  VALUES ('$pickup_date', $max_reservations)
                  ON DUPLICATE KEY UPDATE max_reservations = $max_reservations");
    
    echo "<script>alert('✓ Limit updated for $pickup_date');</script>";
}
?>

<script>
let reservationsByDate = <?php echo json_encode($reservationsByDate); ?>;
let dateSettings = <?php echo json_encode($dateSettings); ?>;
let defaultMax = <?php echo (int)$default_max; ?>;
let currentDate = new Date();
let currentMonth = currentDate.getMonth();
let currentYear = currentDate.getFullYear();
let selectedModalDate = null;

function replaceObject(target, source) {
    Object.keys(target).forEach(key => delete target[key]);
    Object.assign(target, source || {});
}

function getMaxForDate(dateStr) {
    return dateSettings[dateStr] || defaultMax;
}

function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    const header = document.getElementById('currentMonth');
    
    // Clear existing days (keep headers)
    while (grid.children.length > 7) {
        grid.removeChild(grid.lastChild);
    }
    
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                   'July', 'August', 'September', 'October', 'November', 'December'];
    
    header.textContent = `${months[currentMonth]} ${currentYear}`;
    
    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    // Previous month days
    for (let i = firstDay - 1; i >= 0; i--) {
        const day = document.createElement('div');
        day.className = 'calendar-day other-month';
        day.innerHTML = `<span class="calendar-day-number">${daysInPrevMonth - i}</span>`;
        grid.appendChild(day);
    }
    
    // Current month days
    for (let day = 1; day <= daysInMonth; day++) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day';
        
        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const checkDate = new Date(currentYear, currentMonth, day);
        checkDate.setHours(0, 0, 0, 0);
        
        if (checkDate.getTime() === today.getTime()) {
            dayEl.classList.add('today');
        }
        
        const maxForDate = getMaxForDate(dateStr);
        
        if (reservationsByDate[dateStr]) {
            const count = reservationsByDate[dateStr].length;
            dayEl.classList.add('has-reservations');
            
            if (count >= maxForDate) {
                dayEl.classList.add('full');
            } else if (count >= maxForDate - 1) {
                dayEl.classList.add('near-full');
            }
            
            dayEl.innerHTML = `
                <span class="calendar-day-number">${day}</span>
                <span class="calendar-day-count">📦 ${count}/${maxForDate}</span>
            `;
        } else {
            dayEl.innerHTML = `<span class="calendar-day-number">${day}</span>
                <span class="calendar-day-count" style="color: #94a3b8;">0/${maxForDate}</span>
            `;
        }
        
        dayEl.onclick = () => openDateModal(dateStr);
        grid.appendChild(dayEl);
    }
    
    // Next month days
    const totalCells = firstDay + daysInMonth;
    const remaining = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
    
    for (let day = 1; day <= remaining; day++) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day other-month';
        dayEl.innerHTML = `<span class="calendar-day-number">${day}</span>`;
        grid.appendChild(dayEl);
    }
}

function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    } else if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    renderCalendar();
}

function goToToday() {
    currentDate = new Date();
    currentMonth = currentDate.getMonth();
    currentYear = currentDate.getFullYear();
    renderCalendar();
}

function openDateModal(dateStr) {
    selectedModalDate = dateStr;
    const modal = document.getElementById('dateModal');
    const title = document.getElementById('modalTitle');
    const dateTitle = document.getElementById('selectedDateTitle');
    const stats = document.getElementById('dateStats');
    const reservationsDiv = document.getElementById('modalReservations');
    const limitInput = document.getElementById('limitInput');
    const limitDateInput = document.getElementById('limitDateInput');
    
    const date = new Date(dateStr + 'T00:00:00');
    const formattedDate = date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    title.textContent = '📅 ' + date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    dateTitle.textContent = formattedDate;
    limitDateInput.value = dateStr;
    
    const maxForDate = getMaxForDate(dateStr);
    limitInput.value = maxForDate;
    
    const reservations = reservationsByDate[dateStr] || [];
    const count = reservations.length;
    const remaining = maxForDate - count;
    
    // Date stats
    let statusColor = remaining > 0 ? '#16a34a' : '#dc2626';
    let statusText = remaining > 0 ? `${remaining} slot(s) available` : 'Fully booked';
    
    stats.innerHTML = `
        <div class="date-stat">
            <span class="icon">📦</span>
            <div>
                <div class="label">Current</div>
                <div class="value">${count}</div>
            </div>
        </div>
        <div class="date-stat">
            <span class="icon">🎯</span>
            <div>
                <div class="label">Maximum</div>
                <div class="value">${maxForDate}</div>
            </div>
        </div>
        <div class="date-stat">
            <span class="icon">${remaining > 0 ? '✅' : '❌'}</span>
            <div>
                <div class="label">Status</div>
                <div class="value" style="color: ${statusColor}; font-size: 16px;">${statusText}</div>
            </div>
        </div>
    `;
    
    // Reservations list
    if (reservations.length > 0) {
        let html = '';
        reservations.forEach(res => {
            html += `
                <div class="reservation-item ${res.status}">
                    <div class="reservation-icon">🌸</div>
                    <div class="reservation-info">
                        <h5>Order #${res.id} - ${res.product_name}</h5>
                        <p>${res.customer_name} • Qty: ${res.quantity} • ₱${parseFloat(res.total_amount).toFixed(2)}</p>
                    </div>
                    <span class="reservation-status status-${res.status}">${res.status.charAt(0).toUpperCase() + res.status.slice(1)}</span>
                </div>
            `;
        });
        reservationsDiv.innerHTML = html;
    } else {
        reservationsDiv.innerHTML = `
            <div class="no-reservations">
                <div class="icon">📭</div>
                <p>No reservations for this date yet</p>
                <p style="font-size: 13px;">Set the limit above to control capacity</p>
            </div>
        `;
    }
    
    modal.classList.add('show');
}

function closeModal() {
    selectedModalDate = null;
    document.getElementById('dateModal').classList.remove('show');
}

function refreshCalendarData() {
    fetch('pickup_calendar.php?ajax=calendar_data&_=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (!data || data.success !== true) return;

            replaceObject(reservationsByDate, data.reservationsByDate || {});
            replaceObject(dateSettings, data.dateSettings || {});
            defaultMax = parseInt(data.defaultMax || defaultMax, 10);

            const statToday = document.getElementById('statToday');
            const statPending = document.getElementById('statPending');
            const statConfirmed = document.getElementById('statConfirmed');
            if (statToday && data.stats) statToday.textContent = `${data.stats.today} / ${defaultMax}`;
            if (statPending && data.stats) statPending.textContent = data.stats.pending;
            if (statConfirmed && data.stats) statConfirmed.textContent = data.stats.confirmed;

            renderCalendar();

            if (selectedModalDate && document.getElementById('dateModal').classList.contains('show')) {
                openDateModal(selectedModalDate);
            }
        })
        .catch(() => {
            // Silent fail for background refresh
        });
}

// Close modal on outside click
document.getElementById('dateModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Initialize calendar
renderCalendar();
setInterval(refreshCalendarData, 5000);

// Handle limit update with AJAX
document.getElementById('limitForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const updateBtn = document.getElementById('updateBtn');
    const updateMessage = document.getElementById('updateMessage');
    
    updateBtn.disabled = true;
    updateBtn.textContent = 'Updating...';
    
    fetch('pickup_calendar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Update the dateSettings object with the new value
        const pickupDate = formData.get('pickup_date');
        const newLimit = parseInt(formData.get('max_reservations'));
        dateSettings[pickupDate] = newLimit;
        
        // Show success message
        updateMessage.style.display = 'block';
        updateMessage.textContent = '✓ Limit updated successfully!';
        updateMessage.style.background = '#dcfce7';
        updateMessage.style.color = '#15803d';
        
        // Re-render calendar to show updated values
        renderCalendar();
        
        // Also refresh the modal stats
        openDateModal(pickupDate);
        
        // Hide message after 2 seconds
        setTimeout(() => {
            updateMessage.style.display = 'none';
        }, 2000);
        
        updateBtn.disabled = false;
        updateBtn.textContent = 'Update Limit';
    })
    .catch(error => {
        console.error('Error:', error);
        updateMessage.style.display = 'block';
        updateMessage.textContent = 'Error updating limit. Please try again.';
        updateMessage.style.background = '#fee2e2';
        updateMessage.style.color = '#dc2626';
        updateBtn.disabled = false;
        updateBtn.textContent = 'Update Limit';
    });
});
</script>

<script src="../assets/js/script.js"></script>
</body>
</html>
