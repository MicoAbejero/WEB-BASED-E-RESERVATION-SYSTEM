<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

require_permission('pickup.view_own', '../login.php');

$user_id = $_SESSION['user_id'];
$message = "";

// Get user's reservations for pickup dates
$reservations = $conn->query("SELECT r.*, 
                              COALESCE(p.name, r.product_name_snapshot, 'Deleted Product') as product_name,
                              COALESCE(p.image, r.product_image_snapshot) as image,
                              COALESCE(NULLIF(r.pickup_date, '0000-00-00'), DATE_ADD(r.reservation_date, INTERVAL 3 DAY)) as effective_pickup_date
                             FROM reservations r 
                             LEFT JOIN products p ON r.product_id = p.id 
                             WHERE r.user_id = $user_id 
                             AND r.status IN ('pending', 'confirmed')
                             AND COALESCE(NULLIF(r.pickup_date, '0000-00-00'), DATE_ADD(r.reservation_date, INTERVAL 3 DAY)) >= CURDATE()
                             ORDER BY effective_pickup_date ASC");

// Get all upcoming pickup dates for calendar
$upcomingPickups = [];
while ($row = $reservations->fetch_assoc()) {
    $date = $row['effective_pickup_date'];
    if (!isset($upcomingPickups[$date])) {
        $upcomingPickups[$date] = [];
    }
    $upcomingPickups[$date][] = $row;
}

// Get availability data
$default_max = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'max_reservations_per_day'")->fetch_assoc()['setting_value'] ?? 5;

$dateSettings = [];
$settingsResult = $conn->query("SELECT pickup_date, max_reservations FROM date_settings");
while ($row = $settingsResult->fetch_assoc()) {
    $dateSettings[$row['pickup_date']] = $row['max_reservations'];
}

$reservationsByDate = [];
$result = $conn->query("SELECT COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY)) as pickup_date,
                               COUNT(*) as count
                        FROM reservations 
                        WHERE status IN ('pending', 'confirmed') 
                        GROUP BY COALESCE(NULLIF(pickup_date, '0000-00-00'), DATE_ADD(reservation_date, INTERVAL 3 DAY))");
while ($row = $result->fetch_assoc()) {
    $reservationsByDate[$row['pickup_date']] = $row['count'];
}

// Get cart item count
$cart_count = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pickup Calendar - E-Reserve for Crochet Flowers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .calendar-container { background: white; border-radius: 20px; padding: 28px; margin-top: 20px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); border: 1px solid rgba(226, 232, 240, 0.6); }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .calendar-header h3 { margin: 0; font-size: 30px; color: #1e293b; }
        .calendar-nav { display: flex; gap: 12px; }
        .calendar-nav button { padding: 12px 24px; background: linear-gradient(135deg, #f472b6, #c084fc); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 16px; transition: all 0.3s ease; }
        .calendar-nav button:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(244, 114, 182, 0.3); }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-bottom: 24px; }
        .calendar-day-header { text-align: center; padding: 12px; font-weight: 700; color: #64748b; font-size: 16px; }
        .calendar-day { aspect-ratio: 1; border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; background: #f8fafc; border: 2px solid transparent; min-height: 90px; }
        .calendar-day:hover { background: linear-gradient(135deg, #fdf2f8 0%, #faf5ff 100%); border-color: #f472b6; transform: scale(1.02); }
        .calendar-day.other-month { opacity: 0.4; }
        .calendar-day.today { background: linear-gradient(135deg, #f472b6, #c084fc); color: white; }
        .calendar-day.has-pickup { background: linear-gradient(135deg, #dcfce7, #bbf7d0); border-color: #22c55e; }
        .calendar-day.has-pickup:hover { background: linear-gradient(135deg, #bbf7d0, #86efac); }
        .calendar-day.full { background: linear-gradient(135deg, #fee2e2, #fecaca); border-color: #ef4444; cursor: not-allowed; }
        .calendar-day.full:hover { transform: none; background: linear-gradient(135deg, #fee2e2, #fecaca); border-color: #ef4444; }
        .calendar-day.near-full { background: linear-gradient(135deg, #fef3c7, #fde68a); border-color: #f59e0b; }
        .calendar-day.near-full:hover { background: linear-gradient(135deg, #fde68a, #fcd34d); }
        .calendar-day-number { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
        .calendar-day-pickups { font-size: 14px; color: #64748b; font-weight: 600; }
        .calendar-day.today .calendar-day-pickups { color: white; }
        .calendar-day.full .calendar-day-pickups { color: #dc2626; }
        .pickup-details { background: #f8fafc; border-radius: 16px; padding: 24px; margin-top: 24px; }
        .pickup-details h3 { margin: 0 0 20px 0; color: #1e293b; font-size: 20px; }
        .pickup-item { display: flex; gap: 16px; padding: 16px; background: white; border-radius: 12px; margin-bottom: 12px; align-items: center; border-left: 4px solid #22c55e; }
        .pickup-item-icon { width: 48px; height: 48px; background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #fbcfe8 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .pickup-item-info { flex: 1; }
        .pickup-item-info h4 { margin: 0 0 4px 0; color: #1e293b; font-size: 16px; }
        .pickup-item-info p { margin: 0; color: #64748b; font-size: 14px; }
        .pickup-item-status { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-confirmed { background: #dbeafe; color: #2563eb; }
        .legend { display: flex; gap: 24px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 16px; color: #64748b; }
        .legend-color { width: 20px; height: 20px; border-radius: 6px; }
        .legend-today { background: linear-gradient(135deg, #f472b6, #c084fc); }
        .legend-pickup { background: linear-gradient(135deg, #dcfce7, #bbf7d0); border: 2px solid #22c55e; }
        .legend-near-full { background: linear-gradient(135deg, #fef3c7, #fde68a); border: 2px solid #f59e0b; }
        .legend-full { background: linear-gradient(135deg, #fee2e2, #fecaca); border: 2px solid #ef4444; }
        .info-banner { background: linear-gradient(135deg, #dbeafe, #bfdbfe); border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; border: 2px solid #3b82f6; }
        .info-banner .icon { font-size: 24px; }
        .info-banner p { margin: 0; color: #1e40af; font-size: 14px; }
        .page-header h2 { margin: 0 0 8px 0; color: #1e293b; }
        .page-header p { margin: 0; color: #64748b; }
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
        <a href="pickup_calendar.php" class="active"><i class="far fa-calendar-alt"></i> Pickup Calendar</a>
        <a href="profile.php"><i class="far fa-user"></i> My Profile</a>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">

        <div class="topbar">
            <h1>📅 Pickup Calendar</h1>
        </div>

        <?php if ($message): ?>
            <div class="success-message" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; padding: 18px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2);">
                <span style="font-size: 24px;">🎉</span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2>Schedule Your Pickups</h2>
            <p>View availability and your upcoming pickup dates</p>
        </div>

        <div class="info-banner">
            <span class="icon">ℹ️</span>
            <p>This calendar shows all pickup dates and their availability. Green means available, yellow means nearly full, and red means fully booked.</p>
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
                    <div class="legend-color legend-pickup"></div>
                    <span>Has Pickup (You)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-near-full"></div>
                    <span>Nearly Full</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-full"></div>
                    <span>Fully Booked</span>
                </div>
            </div>
            
            <div class="pickup-details" id="pickupDetails">
                <h3>📦 Your Upcoming Pickups</h3>
                <?php if (!empty($upcomingPickups)): ?>
                    <?php foreach ($upcomingPickups as $date => $pickups): ?>
                        <div class="pickup-item">
                            <div class="pickup-item-icon">🌸</div>
                            <div class="pickup-item-info">
                                <h4><?php echo date('F d, Y', strtotime($date)); ?></h4>
                                <p><?php echo count($pickups); ?> item(s) ready for pickup</p>
                            </div>
                            <span class="pickup-item-status status-<?php echo $pickups[0]['status']; ?>">
                                <?php echo ucfirst($pickups[0]['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No upcoming pickups scheduled. <a href="cart.php" style="color: #f472b6;">Reserve items now!</a></p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<script>
const pickupDates = <?php echo json_encode(array_keys($upcomingPickups)); ?>;
const reservationsByDate = <?php echo json_encode($reservationsByDate); ?>;
const dateSettings = <?php echo json_encode($dateSettings); ?>;
const defaultMax = <?php echo $default_max; ?>;
let currentDate = new Date();
let currentMonth = currentDate.getMonth();
let currentYear = currentDate.getFullYear();

function getMaxForDate(dateStr) {
    return dateSettings[dateStr] || defaultMax;
}

function getAvailabilityStatus(dateStr) {
    const max = getMaxForDate(dateStr);
    const current = reservationsByDate[dateStr] || 0;
    const remaining = max - current;
    return { max, current, remaining, status: remaining <= 0 ? 'full' : (remaining <= 1 ? 'near-full' : 'available') };
}

function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    const header = document.getElementById('currentMonth');
    
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
    
    for (let i = firstDay - 1; i >= 0; i--) {
        const day = document.createElement('div');
        day.className = 'calendar-day other-month';
        day.innerHTML = `<span class="calendar-day-number">${daysInPrevMonth - i}</span>`;
        grid.appendChild(day);
    }
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day';
        
        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const checkDate = new Date(currentYear, currentMonth, day);
        checkDate.setHours(0, 0, 0, 0);
        
        if (checkDate.getTime() === today.getTime()) {
            dayEl.classList.add('today');
        }
        
        const avail = getAvailabilityStatus(dateStr);
        
        if (pickupDates.includes(dateStr)) {
            dayEl.classList.add('has-pickup');
            const count = <?php echo json_encode($upcomingPickups); ?>[dateStr]?.length || 0;
            dayEl.innerHTML = `
                <span class="calendar-day-number">${day}</span>
                <span class="calendar-day-pickups">📦 ${count} pickup${count > 1 ? 's' : ''}</span>
            `;
            dayEl.onclick = () => showPickupDetails(dateStr);
        } else if (avail.status === 'full') {
            dayEl.classList.add('full');
            dayEl.innerHTML = `
                <span class="calendar-day-number">${day}</span>
                <span class="calendar-day-pickups">❌ FULL</span>
            `;
        } else if (avail.status === 'near-full') {
            dayEl.classList.add('near-full');
            dayEl.innerHTML = `
                <span class="calendar-day-number">${day}</span>
                <span class="calendar-day-pickups">⚠️ ${avail.remaining} left</span>
            `;
        } else {
            dayEl.innerHTML = `<span class="calendar-day-number">${day}</span>
                <span class="calendar-day-pickups">✅ Available</span>
            `;
        }
        
        grid.appendChild(dayEl);
    }
    
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

function showPickupDetails(dateStr) {
    alert(`Your pickup details for ${dateStr}:\n\nCheck your reservations page for more information.\n\nYou can also add more items to your cart!`);
}

renderCalendar();
</script>

<script src="../assets/js/script.js"></script>
</body>
</html>
