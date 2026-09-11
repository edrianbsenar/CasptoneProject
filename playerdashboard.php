<?php
session_start();
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: /authentication/login");
    exit;
}

require_once __DIR__ . '/includes/database_connect.php';

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$name_parts = explode(' ', trim($full_name));
$first_name = $name_parts[0];

// Handle AJAX skill level update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_skill') {
    header('Content-Type: application/json');
    $new_skill = $_POST['skill_level'] ?? '';
    if (in_array($new_skill, ['Beginner', 'Intermediate', 'Advanced', 'Pro'])) {
        $stmt = $pdo->prepare("UPDATE Users SET skill_level = ? WHERE user_id = ?");
        $stmt->execute([$new_skill, $user_id]);
        echo json_encode(['ok' => true, 'skill' => $new_skill]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid skill level']);
    }
    exit;
}

// Handle AJAX cancel booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    header('Content-Type: application/json');
    $bid = (int)($_POST['booking_id'] ?? 0);
    if ($bid > 0) {
        $stmt = $pdo->prepare("UPDATE Bookings SET status = 'Cancelled' WHERE booking_id = ? AND user_id = ? AND status IN ('Confirmed','Pending Payment','Pending')");
        $stmt->execute([$bid, $user_id]);
        echo json_encode(['ok' => $stmt->rowCount() > 0]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// Fetch user data
$stmt = $pdo->prepare("SELECT skill_level, email FROM Users WHERE user_id = :id");
$stmt->execute(['id' => $user_id]);
$user_data = $stmt->fetch();
$skill_level = $user_data['skill_level'] ?? 'Beginner';
$email = $user_data['email'] ?? '';

// Fetch bookings (with created_at for timer display)
$booking_stmt = $pdo->prepare("
    SELECT b.booking_id, b.booking_reference, b.booking_date, b.start_time, b.end_time, b.status, b.total_price, b.player_count, b.created_at, c.name as court_name
    FROM Bookings b JOIN Courts c ON b.court_id = c.court_id
    WHERE b.user_id = :id AND b.status != 'In Cart'
    ORDER BY b.booking_date DESC, b.start_time DESC
");
$booking_stmt->execute(['id' => $user_id]);
$all_bookings = $booking_stmt->fetchAll();

// Fetch orders
$order_stmt = $pdo->prepare("SELECT order_id, order_date, total_amount, status FROM Orders WHERE user_id = :id ORDER BY order_date DESC");
$order_stmt->execute(['id' => $user_id]);
$all_orders = $order_stmt->fetchAll();

// Stats
$confirmed_count = 0;
$pending_count = 0;
$total_hours = 0;
$total_spent = 0;
foreach ($all_bookings as $b) {
    if ($b['status'] === 'Confirmed') {
        $confirmed_count++;
        $sh = (int)date('H', strtotime($b['start_time']));
        $eh = (int)date('H', strtotime($b['end_time']));
        $total_hours += max(0, $eh - $sh);
        $total_spent += (float)$b['total_price'];
    } elseif ($b['status'] === 'Pending Payment') $pending_count++;
}

// Fetch AI analysis history (gracefully handle missing table)
$ai_analyses = [];
try {
    $ai_stmt = $pdo->prepare("SELECT log_id, stroke_type, accuracy_score, feedback, analyzed_at FROM AI_Analysis_Logs WHERE user_id = ? ORDER BY analyzed_at DESC LIMIT 20");
    $ai_stmt->execute([$user_id]);
    $ai_analyses = $ai_stmt->fetchAll();
} catch (Exception $e) {}
$ai_count = count($ai_analyses);
$ai_avg = 0;
if ($ai_count > 0) {
    $sum = 0;
    foreach ($ai_analyses as $a) $sum += (int)$a['accuracy_score'];
    $ai_avg = round($sum / $ai_count);
}

// Court availability right now (for widget)
$today_date = date('Y-m-d');
$current_hour_int = (int)date('H');
$availability = [];
try {
    $court_list = $pdo->query("SELECT court_id, name, status FROM Courts ORDER BY court_id ASC")->fetchAll();
    foreach ($court_list as $c) {
        $cstatus = $c['status'] ?? 'Available';
        if ($cstatus === 'Maintenance') { $availability[] = ['name'=>$c['name'],'status'=>'Maintenance','icon'=>'<span class="material-symbols-outlined text-gray-400 text-lg">construction</span>']; continue; }
        $cs = $pdo->prepare("SELECT COUNT(*) FROM Bookings WHERE court_id = ? AND booking_date = ? AND status IN ('Confirmed','Pending Payment') AND start_time <= ? AND end_time > ?");
        $time_now = date('H:i:s');
        $cs->execute([$c['court_id'], $today_date, $time_now, $time_now]);
        $is_booked = $cs->fetchColumn() > 0;
        $availability[] = ['name'=>$c['name'], 'status'=>$is_booked ? 'Occupied' : 'Available', 'icon'=>$is_booked ? '<span class="material-symbols-outlined text-accent text-lg">lock</span>' : '<span class="material-symbols-outlined text-green-500 text-lg">check_circle</span>'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>My Dashboard | ShuttleSync</title>
    <?php include __DIR__ . '/includes/tailwind_config.php'; ?>
    <style>
        @keyframes fadeSlideUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
        .anim-in{animation:fadeSlideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
        .anim-d1{animation-delay:.05s;opacity:0;}
        .anim-d2{animation-delay:.1s;opacity:0;}
        .anim-d3{animation-delay:.15s;opacity:0;}
        .sidebar-btn{transition:all .2s ease;border-left:3px solid transparent;}
        .sidebar-btn.active{background:rgba(49,69,230,0.08);color:#3145e6;border-left-color:#3145e6;font-weight:700;}
        .sidebar-btn.active .material-symbols-outlined{font-variation-settings:'FILL' 1;}
        .tab-card{background:#fff;border-radius:20px;border:1px solid rgba(0,0,0,0.06);overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.04);}
        .booking-row{transition:all .15s ease;border-bottom:1px solid #f0f1f5;}
        .booking-row:last-child{border-bottom:none;}
        .booking-row:hover{background:#f8f9fc;}
        .booking-row:hover .booking-actions{opacity:1;}
        .booking-actions{opacity:0;transition:opacity .2s;}
        @keyframes modalIn{from{opacity:0;transform:scale(0.95) translateY(10px);}to{opacity:1;transform:scale(1) translateY(0);}}
        .modal-animate{animation:modalIn .3s cubic-bezier(.22,1,.36,1) forwards;}
        .skill-chip{padding:10px 16px;border-radius:14px;border:2px solid #e8eaf0;cursor:pointer;transition:all .2s;text-align:center;font-weight:600;font-size:13px;}
        .skill-chip:hover{border-color:#3145e6;background:rgba(49,69,230,0.04);}
        .skill-chip.selected{border-color:#3145e6;background:rgba(49,69,230,0.08);color:#3145e6;}
    </style>
</head>
<body class="bg-surface min-h-screen antialiased">

<?php include __DIR__ . '/includes/nav_bar.php'; ?>

<!-- Mobile Tab Bar -->
<div class="md:hidden flex overflow-x-auto border-b border-outline-variant/20 bg-white z-40 px-2 no-scrollbar sticky top-16">
    <button onclick="switchTab('bookings')" id="mob-tab-bookings" class="mob-tab active whitespace-nowrap px-4 py-3.5 text-[11px] font-bold tracking-widest uppercase border-b-2 border-primary text-primary transition-all flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[16px]">calendar_today</span> Bookings
    </button>
    <button onclick="switchTab('orders')" id="mob-tab-orders" class="mob-tab whitespace-nowrap px-4 py-3.5 text-[11px] font-bold tracking-widest uppercase border-b-2 border-transparent text-on-surface-variant transition-all flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[16px]">shopping_bag</span> Orders
    </button>
    <button onclick="switchTab('ai')" id="mob-tab-ai" class="mob-tab whitespace-nowrap px-4 py-3.5 text-[11px] font-bold tracking-widest uppercase border-b-2 border-transparent text-on-surface-variant transition-all flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[16px]">smart_toy</span> AI Coach
    </button>
    <button onclick="switchTab('tips')" id="mob-tab-tips" class="mob-tab whitespace-nowrap px-4 py-3.5 text-[11px] font-bold tracking-widest uppercase border-b-2 border-transparent text-on-surface-variant transition-all flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[16px]">school</span> Tips
    </button>
    <button onclick="switchTab('settings')" id="mob-tab-settings" class="mob-tab whitespace-nowrap px-4 py-3.5 text-[11px] font-bold tracking-widest uppercase border-b-2 border-transparent text-on-surface-variant transition-all flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[16px]">settings</span> Settings
    </button>
</div>

<div class="flex min-h-screen pt-24">

    <!-- Desktop Sidebar -->
    <aside class="hidden md:flex flex-col w-72 bg-white border-r border-outline-variant/20 shrink-0 overflow-y-auto no-scrollbar">
        <!-- Profile Card -->
        <div class="p-6 border-b border-outline-variant/20">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary to-primary-dark flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-primary/20 shrink-0">
                    <?php echo strtoupper(substr($first_name, 0, 1)); ?>
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-on-surface truncate text-sm"><?php echo htmlspecialchars($full_name); ?></p>
                    <p class="text-[11px] text-on-surface-variant font-semibold uppercase tracking-wider mt-0.5" id="sidebarSkill"><?php echo htmlspecialchars($skill_level); ?></p>
                </div>
            </div>
            <!-- Stats -->
            <div class="grid grid-cols-2 gap-3 mt-5">
                <div class="bg-surface rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-primary"><?php echo $total_hours; ?>h</p>
                    <p class="text-[9px] font-bold text-on-surface-variant uppercase tracking-wider">Hours Played</p>
                </div>
                <div class="bg-surface rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-accent">₱<?php echo number_format($total_spent, 0); ?></p>
                    <p class="text-[9px] font-bold text-on-surface-variant uppercase tracking-wider">Total Spent</p>
                </div>
                <div class="bg-surface rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-green-600"><?php echo $confirmed_count; ?></p>
                    <p class="text-[9px] font-bold text-on-surface-variant uppercase tracking-wider">Bookings</p>
                </div>
                <div class="bg-surface rounded-xl p-3 text-center">
                    <p class="text-lg font-bold text-amber-600"><?php echo $ai_count > 0 ? $ai_avg . '%' : '—'; ?></p>
                    <p class="text-[9px] font-bold text-on-surface-variant uppercase tracking-wider">AI Avg Score</p>
                </div>
            </div>
        </div>

        <!-- Nav -->
        <div class="flex-1 p-4 space-y-1">
            <button onclick="switchTab('bookings')" id="side-tab-bookings" class="sidebar-btn active w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left text-sm">
                <span class="material-symbols-outlined text-[20px]">calendar_today</span> My Bookings
            </button>
            <button onclick="switchTab('orders')" id="side-tab-orders" class="sidebar-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left text-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[20px]">shopping_bag</span> My Orders
            </button>
            <button onclick="switchTab('ai')" id="side-tab-ai" class="sidebar-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left text-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[20px]">smart_toy</span> AI Coach
            </button>
            <button onclick="switchTab('tips')" id="side-tab-tips" class="sidebar-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left text-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[20px]">school</span> Tips & Guide
            </button>
            <button onclick="switchTab('settings')" id="side-tab-settings" class="sidebar-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left text-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[20px]">settings</span> Settings
            </button>
            <?php if ($role === 'Admin' || $role === 'Head Manager'): ?>
                <div class="border-t border-outline-variant/20 my-3"></div>
                <a href="admin/" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left text-sm text-on-surface-variant hover:bg-surface transition">
                    <span class="material-symbols-outlined text-[20px]">admin_panel_settings</span> Admin Console
                </a>
            <?php endif; ?>
        </div>

        <!-- Logout -->
        <div class="p-4 border-t border-outline-variant/20">
            <button onclick="toggleLogout()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left text-sm text-accent hover:bg-red-50 transition font-semibold">
                <span class="material-symbols-outlined text-[20px]">logout</span> Log Out
            </button>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto bg-surface pb-24 md:pb-8">
        <div class="max-w-5xl mx-auto p-4 md:p-8">

            <!-- BOOKINGS TAB -->
            <div id="panel-bookings" class="db-panel block">
                <div class="flex justify-between items-end mb-6 anim-in anim-d1">
                    <div>
                        <span class="text-accent text-[11px] font-bold tracking-[0.25em] uppercase mb-1 block">Reservations</span>
                        <h1 class="text-2xl md:text-3xl font-bold">My Bookings</h1>
                    </div>
                    <a href="courtbooking" class="bg-gradient-to-r from-accent to-accent-dark text-white px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 shadow-lg shadow-accent/20 hover:shadow-xl transition active:scale-95">
                        <span class="material-symbols-outlined text-[18px]">add</span> Book Court
                    </a>
                </div>

                <!-- Court Availability Widget -->
                <?php if (!empty($availability)): ?>
                <div class="bg-white rounded-2xl border border-outline-variant/20 p-5 mb-6 shadow-sm anim-in anim-d1">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-lg">stadium</span> Court Availability — Now
                        </h3>
                        <a href="courtbooking" class="text-primary text-[11px] font-bold uppercase tracking-wider hover:underline">Full Schedule →</a>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($availability as $av):
                            $av_cls = $av['status'] === 'Available' ? 'bg-green-50 text-green-700 border-green-200' : ($av['status'] === 'Maintenance' ? 'bg-gray-100 text-gray-500 border-gray-200' : 'bg-red-50 text-accent border-red-200');
                        ?>
                        <div class="flex items-center gap-2 px-3 py-2 rounded-xl border text-xs font-bold <?php echo $av_cls; ?>">
                            <?php echo $av['icon']; ?> <?php echo htmlspecialchars($av['name']); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (count($all_bookings) > 0): ?>
                    <!-- Desktop Table -->
                    <div class="tab-card hidden md:block anim-in anim-d2">
                        <?php foreach ($all_bookings as $i => $booking):
                            $bookDate = date("M d, Y", strtotime($booking['booking_date']));
                            $timeStr = date("h:i A", strtotime($booking['start_time'])) . ' – ' . date("h:i A", strtotime($booking['end_time']));
                            $isPending = $booking['status'] === 'Pending Payment';
                            $isConfirmed = $booking['status'] === 'Confirmed';
                            $isCancelled = $booking['status'] === 'Cancelled' || $booking['status'] === 'Expired';
                            if ($isConfirmed) { $sb = 'bg-green-50 text-green-700 border border-green-200'; $dot = 'bg-green-500'; }
                            elseif ($isPending) { $sb = 'bg-amber-50 text-amber-700 border border-amber-200'; $dot = 'bg-amber-500'; }
                            elseif ($isCancelled) { $sb = 'bg-gray-100 text-gray-500 border border-gray-200'; $dot = 'bg-gray-400'; }
                            else { $sb = 'bg-blue-50 text-blue-700 border border-blue-200'; $dot = 'bg-blue-500'; }
                            $canCancel = in_array($booking['status'], ['Confirmed', 'Pending Payment', 'Pending']);
                        ?>
                        <div class="booking-row flex items-center gap-4 px-6 py-4">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-primary text-lg">stadium</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-bold text-sm text-on-surface truncate"><?php echo htmlspecialchars($booking['court_name']); ?></p>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $sb; ?>">
                                        <?php echo htmlspecialchars($booking['status']); ?>
                                    </span>
                                </div>
                                <p class="text-xs text-on-surface-variant mt-0.5"><?php echo $bookDate; ?> • <?php echo $timeStr; ?> • ₱<?php echo number_format($booking['total_price'], 2); ?></p>
                            </div>
                            <div class="booking-actions flex items-center gap-2">
                                <?php if ($isPending): ?>
                                    <a href="book_and_pay?id=<?php echo $booking['booking_id']; ?>" class="px-3 py-1.5 bg-primary text-white rounded-lg text-[11px] font-bold uppercase tracking-wider hover:shadow-md transition">Pay Now</a>
                                <?php endif; ?>
                                <?php if ($canCancel): ?>
                                    <button onclick="cancelBooking(<?php echo $booking['booking_id']; ?>)" class="px-3 py-1.5 text-accent hover:bg-red-50 rounded-lg text-[11px] font-bold uppercase tracking-wider transition">Cancel</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="md:hidden space-y-3 anim-in anim-d2">
                        <?php foreach ($all_bookings as $booking):
                            $bookDate = date("D, M d, Y", strtotime($booking['booking_date']));
                            $timeStr = date("h:i A", strtotime($booking['start_time'])) . ' – ' . date("h:i A", strtotime($booking['end_time']));
                            $isPending = $booking['status'] === 'Pending Payment';
                            $isConfirmed = $booking['status'] === 'Confirmed';
                            $isCancelled = $booking['status'] === 'Cancelled' || $booking['status'] === 'Expired';
                            if ($isConfirmed) { $sb = 'bg-green-50 text-green-700 border border-green-200'; }
                            elseif ($isPending) { $sb = 'bg-amber-50 text-amber-700 border border-amber-200'; }
                            elseif ($isCancelled) { $sb = 'bg-gray-100 text-gray-500 border border-gray-200'; }
                            else { $sb = 'bg-blue-50 text-blue-700 border border-blue-200'; }
                            $canCancel = in_array($booking['status'], ['Confirmed', 'Pending Payment', 'Pending']);
                        ?>
                        <div class="bg-white rounded-2xl border border-outline-variant/20 p-4 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-primary text-lg">stadium</span>
                                    </div>
                                    <div>
                                        <p class="font-bold text-sm"><?php echo htmlspecialchars($booking['court_name']); ?></p>
                                        <p class="text-[11px] text-on-surface-variant"><?php echo $bookDate; ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase <?php echo $sb; ?>"><?php echo htmlspecialchars($booking['status']); ?></span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-outline-variant/10">
                                <div class="text-xs text-on-surface-variant">
                                    <span class="font-semibold"><?php echo $timeStr; ?></span> • ₱<?php echo number_format($booking['total_price'], 2); ?>
                                </div>
                                <div class="flex gap-2">
                                    <?php if ($isPending): ?>
                                        <a href="book_and_pay?id=<?php echo $booking['booking_id']; ?>" class="px-3 py-1 bg-primary text-white rounded-lg text-[11px] font-bold">Pay Now</a>
                                    <?php endif; ?>
                                    <?php if ($canCancel): ?>
                                        <button onclick="cancelBooking(<?php echo $booking['booking_id']; ?>)" class="px-3 py-1 text-accent hover:bg-red-50 rounded-lg text-[11px] font-bold">Cancel</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="tab-card p-12 text-center anim-in anim-d2">
                        <span class="material-symbols-outlined text-outline-variant text-5xl mb-3 block">event_busy</span>
                        <p class="text-on-surface-variant mb-4 font-medium">No bookings yet</p>
                        <a href="courtbooking" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:shadow-xl transition">
                            <span class="material-symbols-outlined text-[18px]">add</span> Book Your First Court
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ORDERS TAB -->
            <div id="panel-orders" class="db-panel hidden">
                <div class="flex justify-between items-end mb-6 anim-in anim-d1">
                    <div>
                        <span class="text-accent text-[11px] font-bold tracking-[0.25em] uppercase mb-1 block">Pro Shop</span>
                        <h1 class="text-2xl md:text-3xl font-bold">My Orders</h1>
                    </div>
                    <a href="ecommerce" class="bg-gradient-to-r from-primary to-primary-dark text-white px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 shadow-lg shadow-primary/20 hover:shadow-xl transition active:scale-95">
                        <span class="material-symbols-outlined text-[18px]">shopping_bag</span> Shop Gear
                    </a>
                </div>

                <?php if (count($all_orders) > 0): ?>
                    <!-- Desktop Table -->
                    <div class="tab-card hidden md:block anim-in anim-d2">
                        <?php foreach ($all_orders as $order):
                            $orderDate = date("M d, Y", strtotime($order['order_date']));
                            if ($order['status'] === 'Confirmed' || $order['status'] === 'Delivered') { $sb = 'bg-green-50 text-green-700 border border-green-200'; $dot = 'bg-green-500'; }
                            elseif ($order['status'] === 'Pending') { $sb = 'bg-amber-50 text-amber-700 border border-amber-200'; $dot = 'bg-amber-500'; }
                            else { $sb = 'bg-red-50 text-red-700 border border-red-200'; $dot = 'bg-red-500'; }
                        ?>
                        <div class="booking-row flex items-center gap-4 px-6 py-4">
                            <div class="w-10 h-10 rounded-xl bg-surface flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-on-surface-variant text-lg">receipt_long</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-sm text-on-surface">#ORD-<?php echo str_pad($order['order_id'], 5, '0', STR_PAD_LEFT); ?></p>
                                <p class="text-xs text-on-surface-variant mt-0.5"><?php echo $orderDate; ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-sm">₱<?php echo number_format($order['total_amount'], 2); ?></p>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $sb; ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?php echo $dot; ?>"></span>
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="md:hidden space-y-3 anim-in anim-d2">
                        <?php foreach ($all_orders as $order):
                            $orderDate = date("M d, Y", strtotime($order['order_date']));
                            if ($order['status'] === 'Confirmed' || $order['status'] === 'Delivered') { $sb = 'bg-green-50 text-green-700 border border-green-200'; }
                            elseif ($order['status'] === 'Pending') { $sb = 'bg-amber-50 text-amber-700 border border-amber-200'; }
                            else { $sb = 'bg-red-50 text-red-700 border border-red-200'; }
                        ?>
                        <div class="bg-white rounded-2xl border border-outline-variant/20 p-4 shadow-sm flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-surface flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-on-surface-variant">receipt_long</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-sm">#ORD-<?php echo str_pad($order['order_id'], 5, '0', STR_PAD_LEFT); ?></p>
                                <p class="text-[11px] text-on-surface-variant"><?php echo $orderDate; ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-sm">₱<?php echo number_format($order['total_amount'], 2); ?></p>
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?php echo $sb; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="tab-card p-12 text-center anim-in anim-d2">
                        <span class="material-symbols-outlined text-outline-variant text-5xl mb-3 block">inventory_2</span>
                        <p class="text-on-surface-variant mb-4 font-medium">No orders yet</p>
                        <a href="ecommerce" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:shadow-xl transition">
                            <span class="material-symbols-outlined text-[18px]">shopping_bag</span> Browse the Shop
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- AI COACH TAB -->
            <div id="panel-ai" class="db-panel hidden">
                <div class="flex justify-between items-end mb-6 anim-in anim-d1">
                    <div>
                        <span class="text-accent text-[11px] font-bold tracking-[0.25em] uppercase mb-1 block">Biomechanics</span>
                        <h1 class="text-2xl md:text-3xl font-bold">AI Coach</h1>
                    </div>
                    <a href="aimovementanalysis" class="bg-gradient-to-r from-primary to-primary-dark text-white px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 shadow-lg shadow-primary/20 hover:shadow-xl transition active:scale-95">
                        <span class="material-symbols-outlined text-[18px]">add_a_photo</span> New Analysis
                    </a>
                </div>

                <!-- Quick Stats Row -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6 anim-in anim-d1">
                    <div class="bg-white rounded-2xl border border-outline-variant/20 p-4 text-center shadow-sm">
                        <p class="text-2xl font-bold text-primary"><?php echo $ai_count; ?></p>
                        <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider mt-1">Total Scans</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-outline-variant/20 p-4 text-center shadow-sm">
                        <p class="text-2xl font-bold <?php echo $ai_avg >= 70 ? 'text-green-600' : ($ai_avg >= 50 ? 'text-amber-600' : 'text-accent'); ?>"><?php echo $ai_count > 0 ? $ai_avg.'%' : '—'; ?></p>
                        <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider mt-1">Avg Accuracy</p>
                    </div>
                    <?php
                    $best_score = 0; $best_stroke = '—';
                    foreach ($ai_analyses as $a) { if ((int)$a['accuracy_score'] > $best_score) { $best_score = (int)$a['accuracy_score']; $best_stroke = $a['stroke_type']; } }
                    ?>
                    <div class="bg-white rounded-2xl border border-outline-variant/20 p-4 text-center shadow-sm">
                        <p class="text-2xl font-bold text-primary"><?php echo $best_score > 0 ? $best_score.'%' : '—'; ?></p>
                        <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider mt-1">Best Score</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-outline-variant/20 p-4 text-center shadow-sm">
                        <p class="text-lg font-bold text-on-surface"><?php echo $best_stroke; ?></p>
                        <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider mt-1">Top Stroke</p>
                    </div>
                </div>

                <!-- Analysis History -->
                <?php if ($ai_count > 0): ?>
                <div class="tab-card anim-in anim-d2">
                    <div class="px-6 py-4 border-b border-outline-variant/10 flex items-center justify-between">
                        <h3 class="font-bold text-sm">Recent Analyses</h3>
                        <a href="aimovementanalysis" class="text-primary text-[11px] font-bold uppercase tracking-wider hover:underline">Analyze New →</a>
                    </div>
                    <?php foreach ($ai_analyses as $i => $a):
                        $acc = (int)$a['accuracy_score'];
                        $color = $acc >= 75 ? 'text-green-600 bg-green-50 border-green-200' : ($acc >= 50 ? 'text-amber-600 bg-amber-50 border-amber-200' : 'text-accent bg-red-50 border-red-200');
                        $date = $a['analyzed_at'] ? date('M d, Y h:i A', strtotime($a['analyzed_at'])) : '—';
                    ?>
                    <div class="booking-row flex items-center gap-4 px-6 py-4">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-primary text-lg">smart_toy</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-bold text-sm text-on-surface"><?php echo htmlspecialchars($a['stroke_type']); ?></p>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?php echo $color; ?>"><?php echo $acc; ?>%</span>
                            </div>
                            <p class="text-xs text-on-surface-variant mt-0.5 truncate"><?php echo htmlspecialchars(mb_strimwidth($a['feedback'] ?? '', 0, 80, '...')); ?></p>
                        </div>
                        <span class="text-[11px] text-on-surface-variant/60 font-medium shrink-0 hidden sm:block"><?php echo $date; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="tab-card p-12 text-center anim-in anim-d2">
                    <span class="material-symbols-outlined text-outline-variant text-5xl mb-3 block">smart_toy</span>
                    <p class="text-on-surface-variant mb-2 font-medium">No AI analyses yet</p>
                    <p class="text-xs text-on-surface-variant/60 mb-4">Upload a badminton photo to get personalized biomechanical feedback</p>
                    <a href="aimovementanalysis" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:shadow-xl transition">
                        <span class="material-symbols-outlined text-[18px]">add_a_photo</span> Start Analyzing
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- TIPS & GUIDE TAB -->
            <div id="panel-tips" class="db-panel hidden">
                <div class="mb-6 anim-in anim-d1">
                    <span class="text-accent text-[11px] font-bold tracking-[0.25em] uppercase mb-1 block">Learn & Improve</span>
                    <h1 class="text-2xl md:text-3xl font-bold">Tips & Guide</h1>
                </div>

                <!-- Skill-based tips -->
                <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-6 text-white mb-6 anim-in anim-d1 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                    <div class="absolute bottom-0 left-0 w-20 h-20 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2"></div>
                    <div class="relative z-10">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/20 rounded-full text-[10px] font-bold uppercase tracking-widest mb-3">
                            <span class="material-symbols-outlined text-[14px]">sports_tennis</span> <?php echo htmlspecialchars($skill_level); ?> Level
                        </span>
                        <h2 class="text-xl font-bold mb-2" id="tipTitle">Welcome to Your Training Hub</h2>
                        <p class="text-white/80 text-sm leading-relaxed" id="tipDesc">Personalized tips and techniques based on your skill level to help you improve your game.</p>
                    </div>
                </div>

                <!-- Tips Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 anim-in anim-d2" id="tipsGrid">
                    <?php
                    $tips = [
                        'Beginner' => [
                            ['icon' => 'sports', 'title' => 'Grip Fundamentals', 'desc' => 'Master the forehand and backhand grip. Hold the racket like you\'re shaking hands — relaxed but firm. The V between thumb and index finger should align with the racket frame.', 'color' => 'bg-blue-50 text-blue-700'],
                            ['icon' => 'directions_run', 'title' => 'Basic Footwork', 'desc' => 'Start with the split step: a small hop as your opponent hits the shuttle. Always return to center court after each shot. Move with small, quick steps — never cross your feet.', 'color' => 'bg-green-50 text-green-700'],
                            ['icon' => 'sports_tennis', 'title' => 'The Clear Shot', 'desc' => 'The most important beginner stroke. Hit the shuttle high and deep to the back of your opponent\'s court. This gives you time to recover position. Focus on a full arm swing with wrist snap.', 'color' => 'bg-purple-50 text-purple-700'],
                            ['icon' => 'fitness_center', 'title' => 'Build Your Base', 'desc' => 'Badminton demands explosive legs and quick direction changes. Start with lunges, calf raises, and shadow footwork drills. 15 minutes daily builds the foundation for advanced play.', 'color' => 'bg-amber-50 text-amber-700'],
                        ],
                        'Intermediate' => [
                            ['icon' => 'sports_martial_arts', 'title' => 'Smash Technique', 'desc' => 'Generate power from your core, not just your arm. Rotate your hips and shoulders, snap your wrist at contact point, and follow through downward. Practice with a high shuttle feed.', 'color' => 'bg-red-50 text-red-700'],
                            ['icon' => 'swap_horiz', 'title' => 'Deception Shots', 'desc' => 'Use identical preparation for different shots. Show a clear, play a drop. Show a drop, play a net lift. The key is maintaining the same arm speed and body position until the last moment.', 'color' => 'bg-indigo-50 text-indigo-700'],
                            ['icon' => 'speed', 'title' => 'Speed & Agility', 'desc' => 'Add interval sprint training: 30 seconds full speed, 30 seconds rest, repeat 10x. Ladder drills improve foot speed. Shadow badminton (without shuttle) builds muscle memory.', 'color' => 'bg-cyan-50 text-cyan-700'],
                            ['icon' => 'psychology', 'title' => 'Match Strategy', 'desc' => 'Attack your opponent\'s backhand. Use clears to push them deep, then drop shots to bring them forward. Change pace often — don\'t let them settle into a rhythm.', 'color' => 'bg-teal-50 text-teal-700'],
                        ],
                        'Advanced' => [
                            ['icon' => 'precision_manufacturing', 'title' => 'Net Kill Precision', 'desc' => 'Train your net kills with a shuttle on the tape. Practice hitting downward at sharp angles. The key is a firm wrist with minimal backswing — snap and recover instantly.', 'color' => 'bg-orange-50 text-orange-700'],
                            ['icon' => 'groups', 'title' => 'Doubles Tactics', 'desc' => 'In doubles, maintain front-back attack formation when smashing, side-by-side when defending. Communicate with your partner. The server should follow the serve to the net immediately.', 'color' => 'bg-violet-50 text-violet-700'],
                            ['icon' => 'monitor_heart', 'title' => 'Recovery & Endurance', 'desc' => 'At this level, matches are won on endurance. Incorporate court sprints (6 corners), resistance band training, and yoga for flexibility. Sleep 7-8 hours minimum on training days.', 'color' => 'bg-rose-50 text-rose-700'],
                            ['icon' => 'psychology_alt', 'title' => 'Mental Game', 'desc' => 'Develop a between-point routine: breathe, bounce the shuttle, plan the next rally. Stay present — don\'t dwell on errors. Visualize your shots before playing them in pressure situations.', 'color' => 'bg-emerald-50 text-emerald-700'],
                        ],
                        'Pro' => [
                            ['icon' => 'emoji_events', 'title' => 'Game Reading', 'desc' => 'At pro level, anticipate before your opponent strikes. Study their body language, racket angle, and weight distribution. Train by watching matches and predicting shot selection.', 'color' => 'bg-yellow-50 text-yellow-700'],
                            ['icon' => 'tune', 'title' => 'Equipment Optimization', 'desc' => 'Racket string tension, grip size, and shuttle speed all matter at this level. Experiment with higher tensions (28-32 lbs) for control, lower for power. Use BG65 or BG66 strings.', 'color' => 'bg-pink-50 text-pink-700'],
                            ['icon' => 'science', 'title' => 'Periodized Training', 'desc' => 'Structure your training in cycles: off-season (volume), pre-season (intensity), competition (maintenance). Each phase has different load, recovery, and nutrition requirements.', 'color' => 'bg-sky-50 text-sky-700'],
                            ['icon' => 'biotech', 'title' => 'Injury Prevention', 'desc' => 'At pro intensity, injuries are the biggest threat. Warm up 15+ minutes, use ankle braces if needed, ice after sessions, and get regular sports massage. Don\'t play through pain.', 'color' => 'bg-lime-50 text-lime-700'],
                        ],
                    ];
                    $level_tips = $tips[$skill_level] ?? $tips['Beginner'];
                    foreach ($level_tips as $tip):
                    ?>
                    <div class="bg-white rounded-2xl border border-outline-variant/20 p-5 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-3 mb-3">
                            <div class="w-10 h-10 rounded-xl <?php echo $tip['color']; ?> flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-lg"><?php echo $tip['icon']; ?></span>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-on-surface"><?php echo $tip['title']; ?></h3>
                            </div>
                        </div>
                        <p class="text-xs text-on-surface-variant leading-relaxed"><?php echo $tip['desc']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Quick Links -->
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3 anim-in anim-d3">
                    <a href="aimovementanalysis" class="bg-white rounded-2xl border border-outline-variant/20 p-5 shadow-sm hover:shadow-md transition-all group text-center">
                        <span class="material-symbols-outlined text-primary text-3xl mb-2 block group-hover:scale-110 transition-transform">smart_toy</span>
                        <p class="font-bold text-sm">AI Swing Analysis</p>
                        <p class="text-[10px] text-on-surface-variant mt-1">Get real-time biomechanical feedback</p>
                    </a>
                    <a href="courtbooking" class="bg-white rounded-2xl border border-outline-variant/20 p-5 shadow-sm hover:shadow-md transition-all group text-center">
                        <span class="material-symbols-outlined text-accent text-3xl mb-2 block group-hover:scale-110 transition-transform">stadium</span>
                        <p class="font-bold text-sm">Book a Court</p>
                        <p class="text-[10px] text-on-surface-variant mt-1">Reserve your training slot</p>
                    </a>
                    <a href="ecommerce" class="bg-white rounded-2xl border border-outline-variant/20 p-5 shadow-sm hover:shadow-md transition-all group text-center">
                        <span class="material-symbols-outlined text-green-600 text-3xl mb-2 block group-hover:scale-110 transition-transform">shopping_bag</span>
                        <p class="font-bold text-sm">Pro Shop</p>
                        <p class="text-[10px] text-on-surface-variant mt-1">Browse rackets, shoes & gear</p>
                    </a>
                </div>
            </div>

            <!-- SETTINGS TAB -->
            <div id="panel-settings" class="db-panel hidden">
                <div class="mb-6 anim-in anim-d1">
                    <span class="text-accent text-[11px] font-bold tracking-[0.25em] uppercase mb-1 block">Account</span>
                    <h1 class="text-2xl md:text-3xl font-bold">Settings</h1>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 anim-in anim-d2">
                    <!-- Profile Info -->
                    <div class="bg-white rounded-2xl border border-outline-variant/20 p-6 shadow-sm">
                        <h3 class="font-bold text-sm mb-5 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-lg">person</span> Profile Information
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest block mb-1.5">Full Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($full_name); ?>" class="w-full px-4 py-3 bg-surface rounded-xl border border-outline-variant/30 text-sm font-semibold" readonly>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest block mb-1.5">Email Address</label>
                                <input type="email" value="<?php echo htmlspecialchars($email); ?>" class="w-full px-4 py-3 bg-surface rounded-xl border border-outline-variant/30 text-sm font-semibold opacity-60 cursor-not-allowed" disabled>
                            </div>
                        </div>
                    </div>

                    <!-- Skill Level -->
                    <div class="bg-white rounded-2xl border border-outline-variant/20 p-6 shadow-sm">
                        <h3 class="font-bold text-sm mb-5 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-lg">sports_tennis</span> Skill Level
                        </h3>
                        <div class="grid grid-cols-2 gap-3" id="skillGrid">
                            <?php foreach (['Beginner', 'Intermediate', 'Advanced', 'Pro'] as $level):
                                $icons = ['Beginner' => 'sports', 'Intermediate' => 'sports_soccer', 'Advanced' => 'sports_martial_arts', 'Pro' => 'emoji_events'];
                            ?>
                            <div class="skill-chip <?php echo $skill_level === $level ? 'selected' : ''; ?>" onclick="setSkill('<?php echo $level; ?>')" data-skill="<?php echo $level; ?>">
                                <span class="material-symbols-outlined text-lg block mb-1 <?php echo $skill_level === $level ? 'text-primary' : 'text-on-surface-variant'; ?>"><?php echo $icons[$level]; ?></span>
                                <?php echo $level; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-[10px] text-on-surface-variant mt-4 font-medium" id="skillMsg"></p>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Logout Modal -->
<div id="logoutModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="toggleLogout()"></div>
    <div class="relative bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden modal-animate" id="logoutContent">
        <div class="p-8 text-center">
            <div class="w-16 h-16 bg-red-50 text-accent rounded-2xl flex items-center justify-center mx-auto mb-4 border border-red-100">
                <span class="material-symbols-outlined text-3xl">logout</span>
            </div>
            <h3 class="text-xl font-bold mb-2">Ready to leave?</h3>
            <p class="text-sm text-on-surface-variant mb-8">You'll need to log in again to access your dashboard.</p>
            <div class="flex gap-3">
                <button onclick="toggleLogout()" class="flex-1 py-3 bg-surface border border-outline-variant/30 text-on-surface-variant rounded-xl font-bold text-sm hover:bg-surface-container-low transition">Cancel</button>
                <a href="logout" class="flex-1 py-3 bg-accent text-white rounded-xl font-bold text-sm hover:shadow-lg transition text-center">Log Out</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/mobile_nav.php'; ?>

<script>
// Tab Switching
const allTabs = ['bookings', 'orders', 'ai', 'tips', 'settings'];
function switchTab(tab) {
    document.querySelectorAll('.db-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById('panel-' + tab).classList.remove('hidden');
    // Desktop sidebar
    document.querySelectorAll('.sidebar-btn').forEach(b => b.classList.remove('active'));
    const sideBtn = document.getElementById('side-tab-' + tab);
    if (sideBtn) sideBtn.classList.add('active');
    // Mobile tabs
    document.querySelectorAll('.mob-tab').forEach(b => {
        b.classList.remove('active', 'border-primary', 'text-primary');
        b.classList.add('border-transparent', 'text-on-surface-variant');
    });
    const mobTab = document.getElementById('mob-tab-' + tab);
    if (mobTab) {
        mobTab.classList.add('active', 'border-primary', 'text-primary');
        mobTab.classList.remove('border-transparent', 'text-on-surface-variant');
    }
}

// Skill Level Update
function setSkill(level) {
    document.querySelectorAll('.skill-chip').forEach(c => c.classList.remove('selected'));
    document.querySelector('.skill-chip[data-skill="'+level+'"]').classList.add('selected');
    document.getElementById('skillMsg').textContent = '';
    const fd = new FormData();
    fd.append('action', 'update_skill');
    fd.append('skill_level', level);
    fetch('/playerdashboard', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                document.getElementById('skillMsg').textContent = '✓ Skill level updated';
                document.getElementById('skillMsg').className = 'text-[10px] text-green-600 font-bold mt-4';
                document.getElementById('sidebarSkill').textContent = level;
                setTimeout(() => { document.getElementById('skillMsg').textContent = ''; }, 3000);
            }
        });
}

// Cancel Booking
function cancelBooking(bookingId) {
    if (!confirm('Are you sure you want to cancel this booking?')) return;
    const fd = new FormData();
    fd.append('action', 'cancel_booking');
    fd.append('booking_id', bookingId);
    fetch('/playerdashboard', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) location.reload();
            else alert('Could not cancel booking.');
        });
}

// Logout Modal
function toggleLogout() {
    const m = document.getElementById('logoutModal');
    if (m.classList.contains('hidden')) { m.classList.remove('hidden'); }
    else { m.classList.add('hidden'); }
}
</script>

</body>
</html>
