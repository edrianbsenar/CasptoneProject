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
require_once __DIR__ . '/../includes/csrf_helper.php';

$admin_name = $_SESSION['full_name'];
$admin_role = $_SESSION['role'];
$success_message = '';
$error_message = '';

// ==========================================
// 1. HANDLE FORM SUBMISSIONS
// ==========================================
verify_csrf_token();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    // Create Announcement
    if ($_POST['action'] == 'create_announcement') {
        try {
            $message = trim($_POST['message']);
            $target = $_POST['target_audience'];
            $urgency = $_POST['urgency'];
            $status = isset($_POST['is_scheduled']) && $_POST['is_scheduled'] == '1' ? 'Scheduled' : 'Pending';

            if (empty($message)) {
                throw new Exception("Message content cannot be empty.");
            }

            $stmt = $pdo->prepare("INSERT INTO Announcements (message, target_audience, urgency, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$message, $target, $urgency, $status]);
            
            $success_message = "Announcement successfully " . ($status == 'Scheduled' ? "scheduled" : "broadcasted") . "!";
        } catch (Exception $e) {
            $error_message = "Failed to create announcement: " . $e->getMessage();
        }
    }
    
    // Delete Announcement
    if ($_POST['action'] == 'delete_announcement') {
        try {
            $id = (int)$_POST['announcement_id'];
            $stmt = $pdo->prepare("DELETE FROM Announcements WHERE id = ?");
            $stmt->execute([$id]);
            $success_message = "Announcement deleted successfully.";
        } catch (Exception $e) {
            $error_message = "Failed to delete announcement.";
        }
    }
}

// ==========================================
// 2. FETCH DATA
// ==========================================
$count_all = $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn() ?? 0;
$count_registered = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'Player'")->fetchColumn() ?? 0;
$count_pro = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'Player' AND skill_level IN ('Pro', 'Advanced')")->fetchColumn() ?? 0;
$count_staff = $pdo->query("SELECT COUNT(*) FROM Users WHERE role IN ('Admin', 'Head Manager')")->fetchColumn() ?? 0;

$announcements = $pdo->query("SELECT * FROM Announcements ORDER BY created_at DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Broadcast Announcements | ShuttleSync Admin</title>
    <?php include __DIR__ . '/../includes/tailwind_config.php'; ?>
    <style>
        .reach-counter { font-variant-numeric: tabular-nums; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .animate-slide-in { animation: slideIn 0.3s ease-out; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .fade-up { animation: fadeUp 0.4s ease-out both; }
        .fade-up-delay-1 { animation-delay: 0.05s; }
        .fade-up-delay-2 { animation-delay: 0.1s; }
        .fade-up-delay-3 { animation-delay: 0.15s; }
    </style>
</head>
<body class="bg-surface text-on-background min-h-screen">

<?php include __DIR__ . '/includes/admin_header.php'; ?>

<main class="md:ml-[260px] p-4 md:p-8 min-h-screen">

    <!-- Page Header -->
    <div class="mb-8 fade-up">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-[22px]" style="font-variation-settings:'FILL' 1;">campaign</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-on-surface tracking-tight">Broadcast Announcements</h1>
                <p class="text-sm text-on-surface-variant mt-0.5">Engage your athletes with real-time updates and facility news.</p>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center gap-3 shadow-sm animate-slide-in">
            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-emerald-600 text-[18px]">check_circle</span>
            </div>
            <p class="text-emerald-700 text-sm font-semibold"><?php echo htmlspecialchars($success_message); ?></p>
            <button onclick="this.parentElement.remove()" class="ml-auto text-emerald-400 hover:text-emerald-600 transition-colors">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl flex items-center gap-3 shadow-sm animate-slide-in">
            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-red-600 text-[18px]">error</span>
            </div>
            <p class="text-red-700 text-sm font-semibold"><?php echo htmlspecialchars($error_message); ?></p>
            <button onclick="this.parentElement.remove()" class="ml-auto text-red-400 hover:text-red-600 transition-colors">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Two-Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT: Announcement Creator -->
        <section class="lg:col-span-7 bg-white rounded-2xl border border-outline-variant/30 shadow-sm fade-up fade-up-delay-1">
            <div class="px-6 py-5 border-b border-outline-variant/30 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-on-surface tracking-tight">Create Announcement</h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Compose and broadcast a message to your audience.</p>
                </div>
                <span id="draftBadge" class="text-[10px] font-bold uppercase tracking-widest text-primary bg-primary/10 px-3 py-1 rounded-full">Drafting</span>
            </div>
            
            <form method="POST" action="announcements" class="p-6 space-y-5">
                <?php echo get_csrf_input(); ?>
                <input type="hidden" name="action" value="create_announcement">
                <input type="hidden" name="urgency" id="urgencyInput" value="Standard">
                <input type="hidden" name="is_scheduled" id="isScheduledInput" value="0">

                <!-- Message -->
                <div>
                    <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-widest mb-2">Message Content</label>
                    <textarea 
                        name="message" 
                        required 
                        class="w-full min-h-[150px] px-4 py-3 bg-surface border border-outline-variant/60 rounded-xl text-sm text-on-surface placeholder:text-outline focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none resize-none" 
                        placeholder="Write your broadcast message here..."
                        oninput="updateDraftBadge(this)"
                    ></textarea>
                </div>

                <!-- Audience + Urgency Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- Target Audience -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-widest mb-2">Target Audience</label>
                        <div class="relative">
                            <select id="audienceSelect" name="target_audience" class="w-full h-11 px-4 pr-10 bg-surface border border-outline-variant/60 rounded-xl text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all appearance-none cursor-pointer">
                                <option value="All Users">All Users</option>
                                <option value="Registered Players">Registered Players</option>
                                <option value="Pro Athletes">Pro Athletes</option>
                                <option value="Facility Staff">Facility Staff</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-outline text-[18px] pointer-events-none">expand_more</span>
                        </div>
                    </div>

                    <!-- Urgency Level -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-widest mb-2">Urgency Level</label>
                        <div class="flex gap-2">
                            <button type="button" id="btnStandard" class="flex-1 h-11 px-3 border-2 border-primary bg-primary/5 text-primary rounded-xl text-xs font-bold transition-all" onclick="setUrgency('Standard')">
                                Standard
                            </button>
                            <button type="button" id="btnHigh" class="flex-1 h-11 px-3 border border-outline-variant/60 bg-surface text-on-surface-variant rounded-xl text-xs font-bold hover:bg-surface-container-high transition-all flex items-center justify-center gap-1" onclick="setUrgency('High')">
                                <span class="material-symbols-outlined text-[16px]">priority_high</span> High
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="pt-3 flex flex-col sm:flex-row gap-3">
                    <button type="submit" onclick="document.getElementById('isScheduledInput').value='0';" class="flex-1 h-11 bg-primary text-on-primary rounded-xl font-bold text-sm flex items-center justify-center gap-2 hover:bg-primary-dark shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 transition-all">
                        <span class="material-symbols-outlined text-[18px]">send</span> Broadcast Now
                    </button>
                    <button type="submit" onclick="document.getElementById('isScheduledInput').value='1';" class="px-6 h-11 bg-surface border border-outline-variant/60 text-on-surface rounded-xl font-bold text-sm hover:bg-surface-container-high hover:border-outline transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">schedule</span> Schedule for Later
                    </button>
                </div>
            </form>
        </section>

        <!-- RIGHT: Sidebar Cards -->
        <section class="lg:col-span-5 flex flex-col gap-5">
            <!-- Estimated Reach Card -->
            <div class="bg-gradient-to-br from-[#1a1f3d] to-[#0f1229] rounded-2xl p-6 text-white shadow-lg relative overflow-hidden fade-up fade-up-delay-2">
                <div class="absolute top-0 right-0 w-40 h-40 bg-primary/20 rounded-full -translate-y-1/2 translate-x-1/2 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-accent/10 rounded-full translate-y-1/2 -translate-x-1/2 blur-3xl"></div>
                <div class="relative z-10">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-primary-light text-[20px]">groups</span>
                        <h3 class="text-sm font-bold opacity-80">Estimated Reach</h3>
                    </div>
                    <p class="text-4xl font-bold tracking-tight reach-counter" id="reachDisplay"><?php echo number_format($count_all); ?></p>
                    <p class="text-xs opacity-50 mt-2 font-medium">Audience metric synced in real time</p>
                </div>
            </div>

            <!-- Peak Activity Card -->
            <div class="bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-5 flex items-center gap-4 hover:border-primary/30 transition-all fade-up fade-up-delay-2">
                <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-amber-600 text-[20px]">schedule</span>
                </div>
                <div class="min-w-0 flex-grow">
                    <p class="text-sm font-bold text-on-surface">Peak Activity Window</p>
                    <p class="text-xs text-on-surface-variant">Recommended: 17:00 &ndash; 20:00</p>
                </div>
            </div>

            <!-- Templates Card -->
            <div class="bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-5 flex items-center gap-4 hover:border-primary/30 transition-all fade-up fade-up-delay-3">
                <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-primary text-[20px]">article</span>
                </div>
                <div class="min-w-0 flex-grow">
                    <p class="text-sm font-bold text-on-surface">Quick Templates</p>
                    <p class="text-xs text-on-surface-variant">Save typing with pre-set messages</p>
                </div>
            </div>
        </section>

        <!-- FULL WIDTH: Broadcast History -->
        <section class="col-span-12 bg-white rounded-2xl border border-outline-variant/30 shadow-sm overflow-hidden fade-up fade-up-delay-3">
            <div class="px-6 py-5 border-b border-outline-variant/30 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-on-surface tracking-tight">Broadcast History</h2>
                    <p class="text-xs text-on-surface-variant mt-0.5"><?php echo count($announcements); ?> total announcement<?php echo count($announcements) !== 1 ? 's' : ''; ?></p>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-outline-variant/30">
                            <th class="px-6 py-3 text-[10px] font-bold text-outline uppercase tracking-widest">Announcement</th>
                            <th class="px-6 py-3 text-[10px] font-bold text-outline uppercase tracking-widest">Audience</th>
                            <th class="px-6 py-3 text-[10px] font-bold text-outline uppercase tracking-widest">Urgency</th>
                            <th class="px-6 py-3 text-[10px] font-bold text-outline uppercase tracking-widest">Status</th>
                            <th class="px-6 py-3 text-[10px] font-bold text-outline uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <?php if (empty($announcements)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-14 h-14 bg-surface rounded-2xl flex items-center justify-center mb-3">
                                            <span class="material-symbols-outlined text-outline-variant text-[28px]">campaign</span>
                                        </div>
                                        <p class="text-sm font-bold text-on-surface-variant">No announcements yet</p>
                                        <p class="text-xs text-outline mt-1">Create your first broadcast above.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($announcements as $ann): 
                                if ($ann['urgency'] == 'High') {
                                    $urgency_class = 'bg-red-50 text-red-700 border border-red-200';
                                } else {
                                    $urgency_class = 'bg-primary/5 text-primary border border-primary/15';
                                }
                                
                                if ($ann['status'] == 'Scheduled') {
                                    $status_class = 'bg-amber-50 text-amber-700 border border-amber-200';
                                    $status_label = 'Scheduled';
                                    $dot_class = 'bg-amber-400';
                                } else {
                                    $status_class = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                                    $status_label = 'Live';
                                    $dot_class = 'bg-emerald-400 animate-pulse';
                                }
                            ?>
                            <tr class="hover:bg-surface/60 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-on-surface text-sm truncate max-w-[320px]" title="<?php echo htmlspecialchars($ann['message']); ?>">
                                        <?php echo htmlspecialchars($ann['message']); ?>
                                    </div>
                                    <div class="text-[11px] text-outline font-medium mt-1">
                                        <?php echo date("M d, Y \a\\t H:i", strtotime($ann['created_at'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-on-surface-variant">groups</span>
                                        <span class="text-xs font-semibold text-on-surface-variant"><?php echo htmlspecialchars($ann['target_audience']); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo $urgency_class; ?> px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                        <?php echo htmlspecialchars($ann['urgency']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-1.5 h-1.5 rounded-full <?php echo $dot_class; ?>"></div>
                                        <span class="text-xs font-semibold text-on-surface"><?php echo $status_label; ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <form method="POST" action="announcements" onsubmit="return confirm('Delete this announcement permanently?');">
                                            <?php echo get_csrf_input(); ?>
                                            <input type="hidden" name="action" value="delete_announcement">
                                            <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                            <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-outline hover:text-accent transition-all" title="Delete">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</main>

<script>
    // ==========================================
    // UI Logic: Form Urgency Toggle
    // ==========================================
    function setUrgency(level) {
        document.getElementById('urgencyInput').value = level;
        const btnStd = document.getElementById('btnStandard');
        const btnHigh = document.getElementById('btnHigh');

        if (level === 'Standard') {
            btnStd.className = "flex-1 h-11 px-3 border-2 border-primary bg-primary/5 text-primary rounded-xl text-xs font-bold transition-all";
            btnHigh.className = "flex-1 h-11 px-3 border border-outline-variant/60 bg-surface text-on-surface-variant rounded-xl text-xs font-bold hover:bg-surface-container-high transition-all flex items-center justify-center gap-1";
        } else {
            btnHigh.className = "flex-1 h-11 px-3 border-2 border-accent bg-accent/5 text-accent rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1";
            btnStd.className = "flex-1 h-11 px-3 border border-outline-variant/60 bg-surface text-on-surface-variant rounded-xl text-xs font-bold hover:bg-surface-container-high transition-all";
        }
    }

    // ==========================================
    // UI Logic: Draft Badge Update
    // ==========================================
    function updateDraftBadge(textarea) {
        const badge = document.getElementById('draftBadge');
        if (textarea.value.trim().length > 0) {
            badge.textContent = 'Composing';
            badge.className = "text-[10px] font-bold uppercase tracking-widest text-amber-600 bg-amber-50 border border-amber-200 px-3 py-1 rounded-full";
        } else {
            badge.textContent = 'Drafting';
            badge.className = "text-[10px] font-bold uppercase tracking-widest text-primary bg-primary/10 px-3 py-1 rounded-full";
        }
    }

    // ==========================================
    // UI Logic: Audience Reach Calculator
    // ==========================================
    const dbCounts = {
        'All Users': <?php echo $count_all; ?>,
        'Registered Players': <?php echo $count_registered; ?>,
        'Pro Athletes': <?php echo $count_pro; ?>,
        'Facility Staff': <?php echo $count_staff; ?>
    };

    const reachDisplay = document.getElementById('reachDisplay');
    const audienceSelect = document.getElementById('audienceSelect');
    
    audienceSelect.addEventListener('change', (e) => {
        const target = dbCounts[e.target.value] || 0;
        let current = parseInt(reachDisplay.innerText.replace(/,/g, '')) || 0;
        
        const steps = 20;
        const stepValue = (target - current) / steps;
        let currentStep = 0;
        
        clearInterval(window.reachInterval);
        window.reachInterval = setInterval(() => {
            currentStep++;
            current += stepValue;
            reachDisplay.innerText = Math.round(current).toLocaleString();
            
            if (currentStep >= steps) {
                clearInterval(window.reachInterval);
                reachDisplay.innerText = target.toLocaleString();
            }
        }, 20);
    });

    // Auto-hide flash messages after 5 seconds
    document.addEventListener('DOMContentLoaded', () => {
        const alerts = document.querySelectorAll('.animate-slide-in');
        if (alerts.length > 0) {
            setTimeout(() => {
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-8px)';
                    setTimeout(() => alert.remove(), 400);
                });
            }, 5000);
        }
    });
</script>
</body>
</html>
