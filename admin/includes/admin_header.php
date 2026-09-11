<?php
$current_page = basename($_SERVER['PHP_SELF']);
$admin_name_nav = $_SESSION['full_name'] ?? 'Admin';
$admin_role_nav = $_SESSION['role'] ?? 'Manager';

$nav_items = [
    'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
    'court_status.php' => ['icon' => 'stadium', 'label' => 'Courts'],
    'inventory.php' => ['icon' => 'inventory_2', 'label' => 'Inventory'],
    'analytics.php' => ['icon' => 'analytics', 'label' => 'Analytics'],
    'ai_lab.php' => ['icon' => 'psychology', 'label' => 'AI Lab'],
    'announcements.php' => ['icon' => 'campaign', 'label' => 'Announcements'],
    'user_orders.php' => ['icon' => 'receipt_long', 'label' => 'Orders'],
    'user_bookings.php' => ['icon' => 'calendar_month', 'label' => 'Bookings']
];

$admin_notifications = [];
if (isset($pdo)) {
    try {
        $stock_stmt = $pdo->query("SELECT product_id, name, stock_quantity FROM Products WHERE stock_quantity <= 5 AND is_active = 1 LIMIT 5");
        while ($row = $stock_stmt->fetch()) {
            $admin_notifications[] = ['icon' => 'warning', 'color' => 'text-amber-600 bg-amber-50 border-amber-200', 'title' => 'Low Stock', 'desc' => htmlspecialchars($row['name']) . ' (' . $row['stock_quantity'] . ' left)', 'time' => 'Action Needed', 'url' => 'inventory.php'];
        }
        $order_stmt = $pdo->query("SELECT order_number, order_date FROM Orders WHERE status = 'Pending' ORDER BY order_id DESC LIMIT 4");
        while ($row = $order_stmt->fetch()) {
            $admin_notifications[] = ['icon' => 'shopping_bag', 'color' => 'text-primary bg-primary/5 border-primary/20', 'title' => 'Pending Order', 'desc' => '#' . htmlspecialchars($row['order_number']), 'time' => date('M d', strtotime($row['order_date'])), 'url' => 'user_orders.php'];
        }
        $book_stmt = $pdo->query("SELECT booking_reference, booking_date FROM Bookings WHERE status = 'Pending' ORDER BY booking_id DESC LIMIT 4");
        while ($row = $book_stmt->fetch()) {
            $admin_notifications[] = ['icon' => 'event_available', 'color' => 'text-emerald-600 bg-emerald-50 border-emerald-200', 'title' => 'New Booking', 'desc' => $row['booking_reference'], 'time' => date('M d', strtotime($row['booking_date'])), 'url' => 'user_bookings.php'];
        }
    } catch (PDOException $e) {}
}
$admin_alert_count = count($admin_notifications);
?>
<style>
    .admin-sidebar-link { transition: all 0.15s ease; }
    .admin-sidebar-link:hover { transform: translateX(4px); }
    .admin-sidebar-link.active { background: linear-gradient(135deg, rgba(49,69,230,0.1), rgba(49,69,230,0.05)); border-left: 3px solid #3145e6; color: #3145e6; font-weight: 700; }
</style>

<div id="admin-mobile-overlay" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-40 hidden md:hidden transition-opacity opacity-0" onclick="toggleAdminSidebar()"></div>

<aside id="admin-sidebar" class="fixed left-0 top-0 bottom-0 w-[260px] bg-white border-r border-outline-variant/50 flex flex-col z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out shadow-xl md:shadow-md">
    
    <!-- Logo + Brand -->
    <div class="px-5 pt-5 pb-4">
        <div class="flex items-center justify-between mb-5">
            <a href="../index.php" class="flex items-center gap-2.5 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-accent flex items-center justify-center shadow-md group-hover:scale-105 transition-transform">
                    <span class="material-symbols-outlined text-white text-[20px]" style="font-variation-settings:'FILL' 1;">sports_tennis</span>
                </div>
                <div>
                    <span class="text-base font-bold tracking-tight"><span class="text-primary">Shuttle</span><span class="text-accent">Sync</span></span>
                    <p class="text-[9px] text-outline font-bold uppercase tracking-[0.2em] -mt-0.5">Admin Panel</p>
                </div>
            </a>
            <button class="md:hidden w-8 h-8 rounded-lg hover:bg-surface-container flex items-center justify-center transition-colors" onclick="toggleAdminSidebar()">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Admin Profile Card -->
        <div class="bg-gradient-to-br from-surface to-surface-container-low rounded-xl p-3 border border-outline-variant/30">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white shadow-sm shrink-0">
                    <span class="material-symbols-outlined text-[20px]">person</span>
                </div>
                <div class="overflow-hidden min-w-0">
                    <p class="font-bold text-sm text-on-surface truncate" title="<?php echo htmlspecialchars($admin_name_nav); ?>"><?php echo htmlspecialchars($admin_name_nav); ?></p>
                    <p class="text-[10px] text-primary font-bold uppercase tracking-widest truncate"><?php echo htmlspecialchars($admin_role_nav); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="px-4 mb-3 flex gap-2">
        <a href="../index.php" class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-surface-container-low hover:bg-primary/5 border border-outline-variant/30 hover:border-primary/30 rounded-lg text-[11px] font-bold text-on-surface-variant hover:text-primary transition-all">
            <span class="material-symbols-outlined text-[16px]">home</span> Home
        </a>
        <button onclick="toggleAdminNotifications(event)" id="desktopNotifBtn" class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-surface-container-low hover:bg-primary/5 border border-outline-variant/30 hover:border-primary/30 rounded-lg text-[11px] font-bold text-on-surface-variant hover:text-primary transition-all relative">
            <span class="material-symbols-outlined text-[16px]">notifications</span> Alerts
            <?php if ($admin_alert_count > 0): ?>
                <span class="absolute -top-1 -right-1 w-4 h-4 bg-accent text-white text-[9px] font-bold rounded-full flex items-center justify-center shadow-sm"><?php echo $admin_alert_count; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-grow flex flex-col gap-0.5 px-3 overflow-y-auto custom-scrollbar pb-4">
        <p class="text-[9px] font-bold text-outline uppercase tracking-[0.2em] px-3 pt-2 pb-1.5">Navigation</p>
        <?php foreach ($nav_items as $url => $data): 
            $is_active = ($current_page == $url);
            $icon_fill = $is_active ? "'FILL' 1" : "'FILL' 0";
        ?>
            <a class="admin-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13px] <?php echo $is_active ? 'active' : 'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface'; ?>" href="<?php echo $url; ?>">
                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: <?php echo $icon_fill; ?>;"><?php echo $data['icon']; ?></span>
                <span><?php echo $data['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="mt-auto px-4 pt-3 border-t border-outline-variant/30 pb-4">
        <button onclick="toggleAdminLogoutModal()" class="w-full flex items-center gap-3 text-error/80 hover:text-error hover:bg-error/5 px-3 py-2.5 rounded-lg transition-all text-[13px] font-bold outline-none">
            <span class="material-symbols-outlined text-[20px]">logout</span> Sign Out
        </button>
    </div>
</aside>

<!-- Mobile Header -->
<header class="md:hidden fixed top-0 right-0 left-0 h-14 bg-white/90 backdrop-blur-xl border-b border-outline-variant/30 shadow-sm z-30 flex justify-between items-center px-4">
    <div class="flex items-center gap-3">
        <button class="w-9 h-9 rounded-lg hover:bg-surface-container flex items-center justify-center transition-all" onclick="toggleAdminSidebar()">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-primary to-accent flex items-center justify-center">
                <span class="material-symbols-outlined text-white text-[16px]" style="font-variation-settings:'FILL' 1;">sports_tennis</span>
            </div>
            <span class="text-sm font-bold"><span class="text-primary">Shuttle</span><span class="text-accent">Sync</span></span>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="../index.php" class="w-9 h-9 rounded-lg hover:bg-surface-container flex items-center justify-center text-on-surface-variant transition-all">
            <span class="material-symbols-outlined text-[20px]">home</span>
        </a>
        <button id="mobileNotifBtn" onclick="toggleAdminNotifications(event)" class="w-9 h-9 rounded-lg hover:bg-surface-container flex items-center justify-center text-on-surface-variant relative transition-all">
            <span class="material-symbols-outlined text-[20px]">notifications</span>
            <?php if ($admin_alert_count > 0): ?>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-accent rounded-full animate-pulse"></span>
            <?php endif; ?>
        </button>
    </div>
</header>

<!-- Notification Panel -->
<div id="adminNotifPanel" class="hidden fixed top-20 md:top-20 left-4 right-4 md:left-[280px] md:right-auto md:w-80 bg-white border border-outline-variant/50 rounded-2xl shadow-2xl z-[150] overflow-hidden flex flex-col max-h-[70vh]">
    <div class="p-4 border-b border-outline-variant/30 bg-surface flex justify-between items-center shrink-0">
        <h3 class="text-sm font-bold text-on-surface flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[18px]">notifications_active</span> Alerts
        </h3>
        <?php if ($admin_alert_count > 0): ?>
            <span class="text-[10px] bg-accent text-white px-2 py-0.5 rounded-full font-bold"><?php echo $admin_alert_count; ?> New</span>
        <?php endif; ?>
    </div>
    <div class="overflow-y-auto custom-scrollbar flex-grow">
        <?php if (empty($admin_notifications)): ?>
            <div class="p-8 text-center text-on-surface-variant flex flex-col items-center">
                <span class="material-symbols-outlined text-[40px] text-outline-variant mb-2">check_circle</span>
                <p class="text-sm font-bold">All clear!</p>
                <p class="text-[11px]">No pending items.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-outline-variant/20">
                <?php foreach ($admin_notifications as $notif): ?>
                    <a href="<?php echo $notif['url']; ?>" class="p-4 flex gap-3 hover:bg-surface-container-low transition-colors group">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 border <?php echo $notif['color']; ?>">
                            <span class="material-symbols-outlined text-[16px]"><?php echo $notif['icon']; ?></span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold tracking-widest uppercase text-outline group-hover:text-primary transition-colors"><?php echo $notif['title']; ?></p>
                            <p class="text-sm font-bold text-on-surface leading-tight mt-0.5 truncate"><?php echo $notif['desc']; ?></p>
                            <p class="text-[11px] text-outline mt-1"><?php echo $notif['time']; ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Logout Modal -->
<div id="adminLogoutModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="toggleAdminLogoutModal()"></div>
    <div class="relative bg-white w-full max-w-sm rounded-2xl border border-outline-variant/50 shadow-2xl overflow-hidden z-10 scale-95 opacity-0 transition-all duration-300" id="adminLogoutModalContent">
        <div class="p-8 text-center">
            <div class="w-16 h-16 bg-error/10 text-error rounded-2xl flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-3xl">logout</span>
            </div>
            <h3 class="text-xl font-bold text-on-surface mb-2">Sign Out?</h3>
            <p class="text-sm text-on-surface-variant mb-8">You'll be redirected to the homepage.</p>
            <div class="flex gap-3">
                <button onclick="toggleAdminLogoutModal()" class="flex-1 py-3 bg-surface-container text-on-surface-variant border border-outline-variant/50 rounded-xl font-bold text-xs hover:bg-surface-container-high transition-colors">Cancel</button>
                <a href="../logout.php?type=admin" class="flex-1 py-3 bg-error text-white rounded-xl font-bold text-xs hover:bg-error/90 active:scale-95 transition-all shadow-md text-center">Sign Out</a>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleAdminSidebar() {
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('admin-mobile-overlay');
        if (sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            document.body.style.overflow = 'hidden';
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('opacity-0');
            setTimeout(() => overlay.classList.add('hidden'), 300);
            document.body.style.overflow = '';
        }
    }

    function toggleAdminNotifications(event) {
        if(event) event.stopPropagation();
        document.getElementById('adminNotifPanel').classList.toggle('hidden');
    }

    document.addEventListener('click', function(event) {
        const panel = document.getElementById('adminNotifPanel');
        const deskBtn = document.getElementById('desktopNotifBtn');
        const mobBtn = document.getElementById('mobileNotifBtn');
        if (panel && !panel.classList.contains('hidden')) {
            if (!panel.contains(event.target) && (!deskBtn || !deskBtn.contains(event.target)) && (!mobBtn || !mobBtn.contains(event.target))) {
                panel.classList.add('hidden');
            }
        }
    });

    function toggleAdminLogoutModal() {
        const modal = document.getElementById('adminLogoutModal');
        const content = document.getElementById('adminLogoutModalContent');
        if (modal.classList.contains('hidden')) {
            modal.classList.remove('hidden');
            setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); content.classList.add('scale-100', 'opacity-100'); }, 10);
        } else {
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }
    }
</script>