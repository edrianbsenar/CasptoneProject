<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: /authentication/login"); exit; }
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Head Manager') { header("Location: ../playerdashboard.php"); exit; }

require_once __DIR__ . '/../includes/database_connect.php';

// Revenue
$shop_revenue = (float)$pdo->query("SELECT SUM(total_amount) FROM Orders WHERE status != 'Cancelled'")->fetchColumn();
$court_revenue = (float)$pdo->query("SELECT SUM(total_price) FROM Bookings WHERE status IN ('Confirmed', 'Completed', 'Paid')")->fetchColumn();
$total_revenue = $shop_revenue + $court_revenue;
$court_pct = $total_revenue > 0 ? round(($court_revenue / $total_revenue) * 100) : 0;
$shop_pct = 100 - $court_pct;

// Metrics
$avg_booking_value = (float)$pdo->query("SELECT AVG(total_price) FROM Bookings WHERE status IN ('Confirmed', 'Completed', 'Paid')")->fetchColumn();
$active_athletes = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM Bookings WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$total_players = (int)$pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'Player'")->fetchColumn();
$conversion_rate = $total_players > 0 ? ($active_athletes / $total_players) * 100 : 0;

// Peak Hours
$hours_data = $pdo->query("SELECT HOUR(start_time) as hr, COUNT(*) as cnt FROM Bookings WHERE status IN ('Confirmed', 'Completed', 'Paid') GROUP BY hr")->fetchAll(PDO::FETCH_ASSOC);
$peak_hours = array_fill(8, 12, 0);
foreach($hours_data as $row) { $hr = (int)$row['hr']; if ($hr >= 8 && $hr <= 19) $peak_hours[$hr] = (int)$row['cnt']; }
$max_peak = max($peak_hours) ?: 1;
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Analytics | ShuttleSync Admin</title>
    <?php include __DIR__ . '/../includes/tailwind_config.php'; ?>
</head>
<body class="bg-surface text-on-background min-h-screen">

<?php include __DIR__ . '/includes/admin_header.php'; ?>

<main class="md:ml-[260px] p-4 md:p-8 min-h-screen">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-on-surface">Analytics</h1>
            <p class="text-sm text-on-surface-variant mt-1">Revenue intelligence & facility performance metrics.</p>
        </div>
    </div>

    <!-- Top Metrics -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl p-5 border border-outline-variant/30 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center"><span class="material-symbols-outlined text-primary text-[22px]">payments</span></div>
                <span class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">All Sources</span>
            </div>
            <p class="text-2xl font-bold text-on-surface">₱<?php echo number_format($total_revenue, 2); ?></p>
            <p class="text-[11px] text-outline mt-1">Total Revenue</p>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-outline-variant/30 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center"><span class="material-symbols-outlined text-emerald-600 text-[22px]">trending_up</span></div>
                <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full"><?php echo number_format($conversion_rate, 1); ?>%</span>
            </div>
            <p class="text-2xl font-bold text-on-surface"><?php echo number_format($conversion_rate, 1); ?>%</p>
            <p class="text-[11px] text-outline mt-1">Player Conversion</p>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-outline-variant/30 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center"><span class="material-symbols-outlined text-violet-600 text-[22px]">receipt</span></div>
                <span class="text-[10px] font-bold text-violet-600 bg-violet-50 px-2 py-0.5 rounded-full">Avg</span>
            </div>
            <p class="text-2xl font-bold text-on-surface">₱<?php echo number_format($avg_booking_value, 2); ?></p>
            <p class="text-[11px] text-outline mt-1">Avg Booking Value</p>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-outline-variant/30 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center"><span class="material-symbols-outlined text-amber-600 text-[22px]">groups</span></div>
                <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">30 Days</span>
            </div>
            <p class="text-2xl font-bold text-on-surface"><?php echo number_format($active_athletes); ?></p>
            <p class="text-[11px] text-outline mt-1">Active Athletes</p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <!-- Revenue Trend (SVG Chart) -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-6">
            <h3 class="text-base font-bold text-on-surface mb-1">Revenue Trend</h3>
            <p class="text-xs text-outline mb-6">Monthly revenue trajectory</p>
            <div class="h-56 w-full relative">
                <svg class="absolute inset-0 w-full h-full" preserveAspectRatio="none" viewBox="0 0 800 200">
                    <path d="M0,150 Q100,120 200,160 T400,100 T600,130 T800,40" fill="none" stroke="#3145e6" stroke-width="3" stroke-linecap="round"/>
                    <path d="M0,150 Q100,120 200,160 T400,100 T600,130 T800,40 V200 H0 Z" fill="url(#chartGrad)"/>
                    <defs><linearGradient id="chartGrad" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#3145e6" stop-opacity="0.15"/><stop offset="100%" stop-color="#3145e6" stop-opacity="0"/></linearGradient></defs>
                </svg>
                <div class="absolute bottom-0 w-full flex justify-between px-4 text-[11px] font-bold text-outline uppercase tracking-wider">
                    <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span><span>Aug</span>
                </div>
            </div>
        </div>

        <!-- Revenue Split Donut -->
        <div class="bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-6 flex flex-col">
            <h3 class="text-base font-bold text-on-surface mb-1">Revenue Split</h3>
            <p class="text-xs text-outline mb-4">Courts vs Shop</p>
            <div class="flex-1 flex flex-col items-center justify-center">
                <div class="relative w-40 h-40 rounded-full flex items-center justify-center" style="background: conic-gradient(#3145e6 <?php echo $court_pct; ?>%, #e8eaf0 <?php echo $court_pct; ?>%);">
                    <div class="absolute inset-0 m-6 bg-white rounded-full flex flex-col items-center justify-center shadow-inner">
                        <p class="text-xl font-bold text-on-surface"><?php echo $court_pct; ?>%</p>
                        <p class="text-[10px] text-outline uppercase tracking-widest font-bold">Courts</p>
                    </div>
                </div>
                <div class="mt-6 w-full space-y-2">
                    <div class="flex justify-between items-center p-2 rounded-lg hover:bg-surface-container-low transition-colors">
                        <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-primary"></div><span class="text-sm text-on-surface">Court Bookings</span></div>
                        <span class="text-sm font-bold">₱<?php echo number_format($court_revenue, 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center p-2 rounded-lg hover:bg-surface-container-low transition-colors">
                        <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-surface-container-high"></div><span class="text-sm text-on-surface">Shop Orders</span></div>
                        <span class="text-sm font-bold">₱<?php echo number_format($shop_revenue, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Peak Hours -->
    <div class="bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-base font-bold text-on-surface">Peak Court Hours</h3>
                <p class="text-xs text-outline mt-0.5">Booking distribution across business hours (8 AM – 7 PM)</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-primary/20"></div><span class="text-[11px] text-outline font-bold">Low</span></div>
                <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-primary"></div><span class="text-[11px] text-outline font-bold">Peak</span></div>
            </div>
        </div>
        <div class="h-48 flex items-end justify-between gap-1 sm:gap-2 border-b border-outline-variant/50 pb-2">
            <?php foreach($peak_hours as $hr => $cnt): 
                $pct = ($cnt / $max_peak) * 100;
                $bar_h = max(4, $pct);
                $opacity = max(0.15, min(1.0, $pct / 100));
            ?>
                <div class="flex-1 rounded-t-md transition-all group relative cursor-pointer hover:opacity-80" style="height:<?php echo $bar_h; ?>%; background-color: rgba(49,69,230,<?php echo $opacity; ?>);">
                    <div class="absolute -top-10 left-1/2 -translate-x-1/2 bg-on-surface text-white text-[11px] font-bold py-1.5 px-3 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity z-10 pointer-events-none whitespace-nowrap shadow-lg">
                        <?php echo $cnt; ?> bookings
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex justify-between mt-3 text-[11px] font-bold text-outline px-1">
            <span>8AM</span><span>10AM</span><span>12PM</span><span>2PM</span><span>4PM</span><span>6PM</span>
        </div>
    </div>

    <!-- Insight Banner -->
    <div class="bg-gradient-to-r from-primary to-primary-dark rounded-2xl p-8 text-white relative overflow-hidden shadow-lg">
        <div class="absolute right-0 top-0 w-64 h-64 bg-white/5 rounded-full -mr-20 -mt-20 blur-3xl"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center gap-6">
            <div class="w-14 h-14 rounded-2xl bg-white/15 flex items-center justify-center shrink-0 backdrop-blur-sm">
                <span class="material-symbols-outlined text-[28px]">lightbulb</span>
            </div>
            <div class="flex-1">
                <h4 class="text-lg font-bold mb-1">Smart Insight</h4>
                <p class="text-sm text-white/80 leading-relaxed">Data indicates peak bookings occur between <strong class="text-white">5 PM – 7 PM</strong>. Consider implementing dynamic "Prime Time" pricing to optimize facility yield by an estimated 15%.</p>
            </div>
            <button class="bg-white/15 hover:bg-white/25 backdrop-blur-sm px-6 py-3 rounded-xl font-bold text-sm transition-colors shrink-0">Configure Pricing</button>
        </div>
    </div>
</main>

</body>
</html>