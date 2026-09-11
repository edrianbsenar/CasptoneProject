<?php
// Start session
session_start();

// Access Control
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: /authentication/login");
    exit;
}
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Head Manager') {
    header("Location: ../playerdashboard.php");
    exit;
}

require_once __DIR__ . '/../includes/database_connect.php';

$admin_name = $_SESSION['full_name'];
$admin_role = $_SESSION['role'];
$success_message = '';
$error_message = '';

// ==========================================
// DATE NAVIGATION LOGIC FOR COURTS
// ==========================================
$view_date = isset($_GET['date']) ? date('Y-m-d', strtotime($_GET['date'])) : date('Y-m-d');
$display_date = date('M d, Y', strtotime($view_date));
$prev_date = date('Y-m-d', strtotime('-1 day', strtotime($view_date)));
$next_date = date('Y-m-d', strtotime('+1 day', strtotime($view_date)));

// ==========================================
// HANDLE COURT ACTIONS
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    // --- TOGGLE COURT STATUS ---
    if ($_POST['action'] == 'toggle_court_status') {
        try {
            $court_id = (int)$_POST['court_id'];
            $new_status = $_POST['new_status'];
            $notes = $_POST['notes'] ?? '';
            
            $stmt = $pdo->prepare("UPDATE Courts SET status = ?, maintenance_notes = ? WHERE court_id = ?");
            $stmt->execute([$new_status, $notes, $court_id]);
            $success_message = "Court status updated to " . htmlspecialchars($new_status) . "!";
        } catch (Exception $e) {
            $error_message = "Failed to update court status.";
        }
    }
}

// ==========================================
// FETCH COURT DATA & SCHEDULES
// ==========================================
$courts = $pdo->query("SELECT court_id, name, status, maintenance_notes FROM Courts ORDER BY court_id ASC")->fetchAll();

$bookings_stmt = $pdo->prepare("SELECT b.court_id, b.start_time, b.end_time, u.full_name FROM Bookings b JOIN Users u ON b.user_id = u.user_id WHERE b.booking_date = ? AND b.status = 'Confirmed' ORDER BY b.start_time ASC");
$bookings_stmt->execute([$view_date]);
$all_date_bookings = $bookings_stmt->fetchAll();

$court_schedules = [];
foreach($all_date_bookings as $booking) {
    $court_schedules[$booking['court_id']][] = $booking;
}

// Calculate Summary Stats
$total_courts = count($courts);
$count_available = 0;
$count_maintenance = 0;
$count_occupied = 0;

foreach ($courts as $c) {
    if ($c['status'] == 'Available') $count_available++;
    elseif ($c['status'] == 'Maintenance') $count_maintenance++;
    else $count_occupied++;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Court Status & Maintenance | ShuttleSync Admin</title>
    <?php include __DIR__ . '/../includes/tailwind_config.php'; ?>
    <style>
        .court-card { transition: all 0.2s ease; }
        .court-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
        .status-pulse { animation: pulse-ring 2s ease-out infinite; }
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(2.2); opacity: 0; }
        }
        .booking-item { transition: background 0.15s ease; }
        .booking-item:hover { background: rgba(49,69,230,0.04); }
    </style>
</head>
<body class="bg-surface text-on-background min-h-screen">

<!-- Hidden Form for Status Toggle -->
<form id="courtStatusForm" method="POST" action="court_status?date=<?php echo $view_date; ?>" style="display:none;">
    <input type="hidden" name="action" value="toggle_court_status">
    <input type="hidden" name="court_id" id="status_court_id">
    <input type="hidden" name="new_status" id="status_new_status">
    <input type="hidden" name="notes" id="status_notes">
</form>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

    <!-- Main Content -->
    <main class="md:ml-[260px] p-4 md:p-8 min-h-screen">

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#3145e6] to-[#3145e6]/80 flex items-center justify-center shadow-md shadow-[#3145e6]/20">
                            <span class="material-symbols-outlined text-white text-[20px]" style="font-variation-settings:'FILL' 1;">stadium</span>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-on-surface tracking-tight">Court Management</h1>
                            <p class="text-sm text-on-surface-variant">Monitor facility status, manage maintenance, and view bookings.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Date Navigator -->
                    <div class="bg-white rounded-xl border border-outline-variant/30 shadow-sm flex items-center overflow-hidden">
                        <a href="court_status?date=<?php echo $prev_date; ?>" class="px-3 py-2.5 hover:bg-surface-container-low transition-colors flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px] text-on-surface-variant">chevron_left</span>
                        </a>
                        <div class="px-4 py-2.5 border-x border-outline-variant/20 min-w-[140px] text-center">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-0.5">Viewing</p>
                            <p class="text-sm font-bold text-on-surface"><?php echo $display_date; ?></p>
                        </div>
                        <a href="court_status?date=<?php echo $next_date; ?>" class="px-3 py-2.5 hover:bg-surface-container-low transition-colors flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px] text-on-surface-variant">chevron_right</span>
                        </a>
                    </div>

                    <button onclick="reloadPage()" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-outline-variant/30 rounded-xl text-sm font-bold text-on-surface-variant hover:bg-surface-container-low hover:border-outline-variant/50 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-[18px]" id="reload-icon">refresh</span>
                        <span class="hidden sm:inline">Refresh</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success_message)): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3 shadow-sm">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-emerald-600 text-[20px]">check_circle</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-emerald-800">Success</p>
                    <p class="text-sm text-emerald-700"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 shadow-sm">
                <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-red-600 text-[20px]">error</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-red-800">Error</p>
                    <p class="text-sm text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Total Courts -->
            <div class="bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-5 group hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-surface-container flex items-center justify-center">
                        <span class="material-symbols-outlined text-on-surface-variant text-[20px]">sports_tennis</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Total</span>
                </div>
                <p class="text-3xl font-extrabold text-on-surface tracking-tight"><?php echo str_pad($total_courts, 2, '0', STR_PAD_LEFT); ?></p>
                <p class="text-xs text-on-surface-variant mt-1">Registered courts</p>
            </div>

            <!-- Available -->
            <div class="bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-5 group hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-emerald-600 text-[20px]">check_circle</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-600">Open</span>
                </div>
                <p class="text-3xl font-extrabold text-on-surface tracking-tight"><?php echo str_pad($count_available, 2, '0', STR_PAD_LEFT); ?></p>
                <p class="text-xs text-on-surface-variant mt-1">Courts available</p>
            </div>

            <!-- Occupied -->
            <div class="bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-5 group hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-[#3145e6]/5 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[#3145e6] text-[20px]">schedule</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-[#3145e6]">Active</span>
                </div>
                <p class="text-3xl font-extrabold text-on-surface tracking-tight"><?php echo str_pad($count_occupied, 2, '0', STR_PAD_LEFT); ?></p>
                <p class="text-xs text-on-surface-variant mt-1">Currently in use</p>
            </div>

            <!-- Maintenance -->
            <div class="bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-5 group hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-[#ed4a30]/5 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[#ed4a30] text-[20px]">build</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-[#ed4a30]">Down</span>
                </div>
                <p class="text-3xl font-extrabold text-on-surface tracking-tight"><?php echo str_pad($count_maintenance, 2, '0', STR_PAD_LEFT); ?></p>
                <p class="text-xs text-on-surface-variant mt-1">Under maintenance</p>
            </div>
        </div>

        <!-- Court Cards Grid -->
        <div id="court-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-5">
            <?php foreach ($courts as $court): 
                $c_id = $court['court_id'];
                $current_bookings = $court_schedules[$c_id] ?? [];
                $booking_count = count($current_bookings);
                
                // Status-specific styling
                if ($court['status'] == 'Available') {
                    $status_color = 'emerald';
                    $status_bg = 'bg-emerald-50';
                    $status_text = 'text-emerald-700';
                    $status_border = 'border-emerald-200';
                    $icon_bg = 'bg-emerald-50';
                    $icon_color = 'text-emerald-600';
                    $card_icon = 'stadium';
                    $btn_text = 'Set Maintenance';
                    $btn_action = "setMaintenance($c_id)";
                    $btn_class = 'bg-white border-outline-variant/40 text-on-surface-variant hover:border-[#ed4a30]/40 hover:text-[#ed4a30] hover:bg-[#ed4a30]/5';
                    $accent_bar = 'bg-emerald-500';
                } elseif ($court['status'] == 'Maintenance') {
                    $status_color = 'red';
                    $status_bg = 'bg-red-50';
                    $status_text = 'text-red-700';
                    $status_border = 'border-red-200';
                    $icon_bg = 'bg-red-50';
                    $icon_color = 'text-red-500';
                    $card_icon = 'handyman';
                    $btn_text = 'Mark Available';
                    $btn_action = "setAvailable($c_id)";
                    $btn_class = 'bg-[#ed4a30] text-white hover:bg-[#d13a23] border-transparent shadow-sm shadow-[#ed4a30]/20';
                    $accent_bar = 'bg-[#ed4a30]';
                } else {
                    $status_color = 'blue';
                    $status_bg = 'bg-[#3145e6]/5';
                    $status_text = 'text-[#3145e6]';
                    $status_border = 'border-[#3145e6]/20';
                    $icon_bg = 'bg-[#3145e6]/5';
                    $icon_color = 'text-[#3145e6]';
                    $card_icon = 'sports_tennis';
                    $btn_text = 'Set Maintenance';
                    $btn_action = "setMaintenance($c_id)";
                    $btn_class = 'bg-white border-outline-variant/40 text-on-surface-variant hover:border-[#ed4a30]/40 hover:text-[#ed4a30] hover:bg-[#ed4a30]/5';
                    $accent_bar = 'bg-[#3145e6]';
                }
            ?>
            <div class="court-card bg-white rounded-2xl border border-outline-variant/30 shadow-sm overflow-hidden flex flex-col relative">
                
                <!-- Top accent bar -->
                <div class="h-1 <?php echo $accent_bar; ?>"></div>

                <!-- Card Header -->
                <div class="p-5 pb-4">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl <?php echo $icon_bg; ?> flex items-center justify-center relative">
                                <span class="material-symbols-outlined <?php echo $icon_color; ?> text-[22px]" style="font-variation-settings: 'FILL' 1;"><?php echo $card_icon; ?></span>
                                <?php if($court['status'] == 'Available'): ?>
                                    <span class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white"></span>
                                    <span class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-emerald-400 rounded-full status-pulse"></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-on-surface text-[15px] leading-tight"><?php echo htmlspecialchars($court['name']); ?></h3>
                                <p class="text-xs text-on-surface-variant mt-0.5">Court #<?php echo $c_id; ?></p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg <?php echo $status_bg; ?> <?php echo $status_text; ?> border <?php echo $status_border; ?> text-[11px] font-bold uppercase tracking-wide">
                            <span class="w-1.5 h-1.5 rounded-full <?php echo str_replace('text-', 'bg-', $status_text); ?>"></span>
                            <?php echo htmlspecialchars($court['status']); ?>
                        </span>
                    </div>

                    <?php if ($court['status'] == 'Maintenance'): ?>
                        <!-- Maintenance Info -->
                        <div class="bg-red-50/60 border border-red-100 rounded-xl p-3.5">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="material-symbols-outlined text-red-500 text-[16px]">warning</span>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-red-600">Maintenance Notes</p>
                            </div>
                            <p class="text-sm text-red-800 font-medium leading-relaxed"><?php echo htmlspecialchars($court['maintenance_notes'] ?: 'General maintenance in progress'); ?></p>
                        </div>
                    <?php else: ?>
                        <!-- Bookings List -->
                        <div class="border border-outline-variant/20 rounded-xl overflow-hidden">
                            <div class="px-3.5 py-2.5 bg-surface-container-low/50 flex items-center justify-between border-b border-outline-variant/10">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-on-surface-variant/60 text-[16px]">event_note</span>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Today's Reservations</p>
                                </div>
                                <span class="text-[11px] font-bold <?php echo $booking_count > 0 ? 'text-[#3145e6] bg-[#3145e6]/5' : 'text-on-surface-variant/50 bg-surface-container'; ?> px-2 py-0.5 rounded-md"><?php echo $booking_count; ?> <?php echo $booking_count === 1 ? 'slot' : 'slots'; ?></span>
                            </div>
                            <div class="max-h-[160px] overflow-y-auto custom-scrollbar">
                                <?php if(empty($current_bookings)): ?>
                                    <div class="p-5 text-center">
                                        <span class="material-symbols-outlined text-on-surface-variant/20 text-[32px] block mb-1">event_busy</span>
                                        <p class="text-xs text-on-surface-variant/50 italic">No bookings for this date</p>
                                    </div>
                                <?php else: ?>
                                    <div class="divide-y divide-outline-variant/10">
                                        <?php foreach($current_bookings as $b): ?>
                                            <div class="booking-item px-3.5 py-2.5 flex items-center justify-between">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <div class="w-8 h-8 rounded-lg bg-[#3145e6]/5 flex items-center justify-center shrink-0">
                                                        <span class="material-symbols-outlined text-[#3145e6] text-[14px]">schedule</span>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-xs font-bold text-on-surface"><?php echo htmlspecialchars($b['full_name']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                                                    <span class="text-[11px] font-bold text-[#3145e6] bg-[#3145e6]/5 px-2 py-0.5 rounded-md">
                                                        <?php echo date('H:i', strtotime($b['start_time'])); ?>
                                                    </span>
                                                    <span class="text-on-surface-variant/30 text-[10px]">-</span>
                                                    <span class="text-[11px] font-medium text-on-surface-variant">
                                                        <?php echo date('H:i', strtotime($b['end_time'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Card Action -->
                <div class="px-5 pb-5 mt-auto">
                    <button onclick="<?php echo $btn_action; ?>" class="w-full py-2.5 border rounded-xl text-sm font-bold transition-all active:scale-[0.98] <?php echo $btn_class; ?>">
                        <span class="material-symbols-outlined text-[16px] align-middle mr-1.5"><?php echo $court['status'] == 'Maintenance' ? 'check' : 'build'; ?></span>
                        <?php echo $btn_text; ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Empty State (if no courts) -->
        <?php if (empty($courts)): ?>
            <div class="bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-surface-container flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-on-surface-variant/30 text-[40px]">sports_tennis</span>
                </div>
                <h3 class="text-lg font-bold text-on-surface mb-1">No Courts Found</h3>
                <p class="text-sm text-on-surface-variant">There are no courts registered in the system yet.</p>
            </div>
        <?php endif; ?>

    </main>

<script>
    function reloadPage() {
        const icon = document.getElementById('reload-icon');
        icon.classList.add('animate-spin');
        setTimeout(() => {
            window.location.reload();
        }, 600);
    }

    function setMaintenance(courtId) {
        let note = prompt("Enter reason for maintenance (e.g., Net Repair, Deep Cleaning):");
        if (note !== null && note.trim() !== '') {
            document.getElementById('status_court_id').value = courtId;
            document.getElementById('status_new_status').value = 'Maintenance';
            document.getElementById('status_notes').value = note.trim();
            document.getElementById('courtStatusForm').submit();
        }
    }

    function setAvailable(courtId) {
        if (confirm("Mark this court as available and end maintenance?")) {
            document.getElementById('status_court_id').value = courtId;
            document.getElementById('status_new_status').value = 'Available';
            document.getElementById('status_notes').value = '';
            document.getElementById('courtStatusForm').submit();
        }
    }
</script>
</body>
</html>
