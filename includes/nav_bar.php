<?php
// nav_bar.php

$current_page = basename($_SERVER['PHP_SELF']);

function getNavClass($page_name, $current_page) {
    $base_class = "font-body-md text-sm transition-colors ";
    if ($current_page === $page_name) {
        return $base_class . "text-primary font-bold border-b-2 border-accent pb-1";
    } else {
        return $base_class . "text-on-surface-variant font-medium hover:text-primary";
    }
}

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$dash_link = "/authentication/login";

if ($is_logged_in) {
    $dash_link = ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Head Manager') ? '/admin/dashboard' : '/playerdashboard';
}

if (!isset($cart_count) && isset($pdo) && isset($_SESSION['user_id'])) {
    try {
        $cart_stmt = $pdo->prepare("SELECT SUM(quantity) FROM Cart_Items WHERE user_id = ?");
        $cart_stmt->execute([$_SESSION['user_id']]);
        $cart_count = $cart_stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        $cart_count = 0;
    }
} elseif (!isset($cart_count)) {
    $cart_count = 0;
}

$announcements = [];
$announcement_count = 0;

if (isset($pdo)) {
    try {
        $user_audiences = ['All Users'];
        if ($is_logged_in) {
            $role = $_SESSION['role'] ?? '';
            if ($role === 'Admin' || $role === 'Head Manager') {
                $user_audiences[] = 'Facility Staff';
            } elseif ($role === 'Player') {
                $user_audiences[] = 'Registered Players';
                $skill = $_SESSION['skill_level'] ?? $_SESSION['skill'] ?? 'Beginner';
                if (in_array($skill, ['Pro', 'Advanced'])) {
                    $user_audiences[] = 'Pro Athletes';
                }
            }
        }
        $placeholders = implode(',', array_fill(0, count($user_audiences), '?'));
        $stmt = $pdo->prepare("SELECT * FROM Announcements WHERE status != 'Scheduled' AND target_audience IN ($placeholders) ORDER BY created_at DESC LIMIT 5");
        $stmt->execute($user_audiences);
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $announcement_count = count($announcements);
    } catch (PDOException $e) {
        $announcements = [];
    }
}
?>

<header class="fixed top-4 left-1/2 -translate-x-1/2 w-[calc(100%-32px)] max-w-7xl z-50 bg-white/80 backdrop-blur-xl border border-outline-variant/30 shadow-lg shadow-black/5 rounded-2xl transition-all duration-300" id="mainHeader">
    <nav class="flex justify-between items-center h-16 px-4 md:px-8 max-w-7xl mx-auto">
        
        <!-- Logo -->
        <div class="flex items-center gap-2">
            <img alt="ShuttleSync Logo" class="w-8 h-8" src="/img/logo.png" onerror="this.src='https://placehold.co/32x32/00668a/ffffff?text=S'">
            <a href="/" class="text-xl font-bold tracking-tight">
                <span class="text-primary">Shuttle</span><span class="text-accent">Sync</span>
            </a>
        </div>
        
        <!-- Desktop Links -->
        <div class="hidden md:flex items-center gap-8">
            <a class="<?php echo getNavClass('index.php', $current_page); ?>" href="/">Home</a>
            <a class="<?php echo getNavClass('courtbooking.php', $current_page); ?>" href="/courtbooking">Courts</a>
            <a class="<?php echo getNavClass('ecommerce.php', $current_page); ?>" href="/ecommerce">Shop</a>
            <a class="<?php echo getNavClass('aimovementanalysis.php', $current_page); ?>" href="/aimovementanalysis">AI Coach</a>
        </div>
        
        <!-- Right Side Actions -->
        <div class="flex items-center gap-5">
            
            <button onclick="if(window.tidioChatApi){window.tidioChatApi.open();}else{alert('Live support is loading...');}" class="text-on-surface-variant hover:text-primary transition-all active:scale-95 hidden md:block" title="Live Support">
                <span class="material-symbols-outlined text-[26px]">support_agent</span>
            </button>

            <!-- Notifications Dropdown -->
            <div class="relative">
                <button class="relative text-on-surface-variant hover:text-primary transition-all active:scale-95" id="notifButton" onclick="toggleNotifications(event)">
                    <span class="material-symbols-outlined text-[26px]">notifications</span>
                    <?php if ($announcement_count > 0): ?>
                        <span class="absolute -top-1 -right-1 flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-error opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-error border-2 border-surface"></span>
                        </span>
                    <?php endif; ?>
                </button>

                <div id="notifDropdown" class="absolute right-0 mt-2 w-80 bg-surface-container-lowest border border-outline-variant/50 rounded-xl shadow-xl overflow-hidden z-[60] origin-top-right hidden">
                    <div class="p-4 border-b border-outline-variant/30 bg-surface-bright flex justify-between items-center">
                        <h3 class="font-headline-md text-sm font-bold text-on-surface">Announcements</h3>
                        <?php if ($announcement_count > 0): ?>
                            <span class="text-[10px] bg-accent text-white px-2 py-0.5 rounded-full font-bold"><?php echo $announcement_count; ?> New</span>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-[300px] overflow-y-auto custom-scrollbar">
                        <?php if (empty($announcements)): ?>
                            <div class="p-6 text-center text-on-surface-variant font-body-md text-sm">
                                <span class="material-symbols-outlined text-outline block mb-2 text-[32px]">notifications_paused</span>
                                You're all caught up!
                            </div>
                        <?php else: ?>
                            <div class="divide-y divide-outline-variant/20">
                                <?php foreach ($announcements as $ann): ?>
                                    <?php 
                                        $isHigh = ($ann['urgency'] === 'High');
                                        $iconColor = $isHigh ? 'text-error bg-error-container/20' : 'text-primary bg-primary-container/20';
                                        $icon = $isHigh ? 'campaign' : 'info';
                                    ?>
                                    <div class="p-4 hover:bg-surface-container-low transition-colors cursor-default">
                                        <div class="flex gap-3">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 <?php echo $iconColor; ?>">
                                                <span class="material-symbols-outlined text-[18px]"><?php echo $icon; ?></span>
                                            </div>
                                            <div>
                                                <p class="text-sm font-body-md text-on-surface leading-snug"><?php echo htmlspecialchars($ann['message']); ?></p>
                                                <div class="flex items-center gap-2 mt-2">
                                                    <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider"><?php echo date("M d, h:i A", strtotime($ann['created_at'])); ?></span>
                                                    <?php if($isHigh): ?>
                                                        <span class="text-[9px] text-error font-bold uppercase tracking-widest border border-error/30 px-1.5 rounded">Urgent</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($is_logged_in): ?>
                <a href="/cart" class="relative text-on-surface-variant hover:text-primary transition-all active:scale-95">
                    <span class="material-symbols-outlined text-[26px]">shopping_cart</span>
                    <?php if ($cart_count > 0): ?>
                        <span class="absolute -top-1.5 -right-2 bg-error text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-sm ring-2 ring-surface"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
                <!-- Profile Dropdown -->
                <div class="relative ml-1">
                    <button class="text-on-surface-variant hover:text-primary transition-all active:scale-95" id="profileButton" onclick="toggleProfileMenu(event)">
                        <span class="material-symbols-outlined text-[28px]">account_circle</span>
                    </button>
                    <div id="profileDropdown" class="absolute right-0 mt-2 w-56 bg-surface-container-lowest border border-outline-variant/50 rounded-xl shadow-xl overflow-hidden z-[60] origin-top-right hidden">
                        <div class="p-3 border-b border-outline-variant/30 bg-surface-bright">
                            <p class="text-sm font-bold text-on-surface truncate"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></p>
                            <p class="text-[11px] text-outline truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
                        </div>
                        <div class="py-1">
                            <a href="/playerdashboard" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface-variant hover:bg-surface-container-low hover:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[20px]">dashboard</span> My Dashboard
                            </a>
                            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['Admin', 'Head Manager'])): ?>
                            <a href="/admin/dashboard" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface-variant hover:bg-surface-container-low hover:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[20px]">admin_panel_settings</span> Admin Panel
                            </a>
                            <?php endif; ?>
                            <div class="border-t border-outline-variant/30 my-1"></div>
                            <a href="/logout" class="flex items-center gap-3 px-4 py-2.5 text-sm text-error hover:bg-error/5 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">logout</span> Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a href="/authentication/login" class="hidden md:inline-block font-bold text-sm text-on-surface-variant hover:text-primary transition-colors">Log In</a>
                <a href="/authentication/register" class="hidden md:inline-block btn-accent px-5 py-2 rounded-lg text-sm font-bold active:scale-95 shadow-md shadow-accent/20">Register</a>
            <?php endif; ?>
            
            <button class="md:hidden text-on-surface-variant hover:text-primary transition-colors" onclick="document.getElementById('mobile-menu')?.classList.toggle('hidden')">
                <span class="material-symbols-outlined text-[28px]">menu</span>
            </button>
        </div>
    </nav>
</header>

<!-- Tidio Integration Script -->
<script src="//code.tidio.co/xrwsq4cmchufdxjlebbrzzag1vlabwsq.js" async></script>

<script>
    window.addEventListener('scroll', () => {
        const header = document.getElementById('mainHeader');
        if (window.scrollY > 10) {
            header.classList.add('shadow-md');
            header.classList.remove('shadow-sm');
        } else {
            header.classList.remove('shadow-md');
            header.classList.add('shadow-sm');
        }
    });

    function toggleNotifications(e) {
        e.stopPropagation();
        const dropdown = document.getElementById('notifDropdown');
        const profileDrop = document.getElementById('profileDropdown');
        if (profileDrop && !profileDrop.classList.contains('hidden')) profileDrop.classList.add('hidden');
        dropdown.classList.toggle('hidden');
    }

    function toggleProfileMenu(e) {
        e.stopPropagation();
        const dropdown = document.getElementById('profileDropdown');
        const notifDrop = document.getElementById('notifDropdown');
        if (notifDrop && !notifDrop.classList.contains('hidden')) notifDrop.classList.add('hidden');
        dropdown.classList.toggle('hidden');
    }

    document.addEventListener('click', function(event) {
        const notifDropdown = document.getElementById('notifDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        if (notifDropdown && !notifDropdown.classList.contains('hidden')) {
            notifDropdown.classList.add('hidden');
        }
        if (profileDropdown && !profileDropdown.classList.contains('hidden')) {
            profileDropdown.classList.add('hidden');
        }
    });
</script>
