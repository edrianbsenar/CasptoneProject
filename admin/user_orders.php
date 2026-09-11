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
// HANDLE ORDER ACTIONS
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_order_status') {
        try {
            $order_id = (int)$_POST['order_id'];
            $new_status = $_POST['status']; // 'Completed' or 'Cancelled'
            
            $stmt = $pdo->prepare("UPDATE Orders SET status = ? WHERE order_id = ?");
            $stmt->execute([$new_status, $order_id]);
            
            $success_message = "Order #ORD-" . str_pad($order_id, 5, '0', STR_PAD_LEFT) . " marked as " . htmlspecialchars($new_status) . ".";
        } catch (Exception $e) {
            $error_message = "Failed to update order status.";
        }
    }
}

// ==========================================
// FETCH ORDER DATA & METRICS
// ==========================================
// Metrics
$pending_count = $pdo->query("SELECT COUNT(*) FROM Orders WHERE status = 'Pending'")->fetchColumn() ?? 0;
$processing_count = $pdo->query("SELECT COUNT(*) FROM Orders WHERE status IN ('Confirmed', 'Completed', 'Delivered')")->fetchColumn() ?? 0;

// Fetch All Orders
$orders_stmt = $pdo->query("
    SELECT o.order_id, o.order_date, o.total_amount, o.status, u.full_name 
    FROM Orders o 
    JOIN Users u ON o.user_id = u.user_id 
    ORDER BY o.order_date DESC
");
$all_orders = $orders_stmt->fetchAll();

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>User Orders Queue | ShuttleSync Admin</title>
    <?php include __DIR__ . '/../includes/tailwind_config.php'; ?>
</head>
<body class="bg-background min-h-screen">

<form id="orderActionForm" method="POST" action="user_orders" style="display:none;">
    <input type="hidden" name="action" value="update_order_status">
    <input type="hidden" name="order_id" id="action_order_id">
    <input type="hidden" name="status" id="action_status">
</form>

<?php include __DIR__ . '/includes/admin_header.php'; ?>


    <main class="flex-1 ml-0 md:ml-64 p-margin-md min-h-screen">
        
        <?php if (!empty($success_message)): ?>
            <div class="mb-6 p-4 bg-primary-container/20 border border-primary/50 rounded-lg flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-primary">check_circle</span>
                <p class="text-primary font-body-md text-sm font-bold"><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 bg-error-container/20 border border-error/50 rounded-lg flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-error">error</span>
                <p class="text-error font-body-md text-sm font-bold"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <div class="mb-gutter flex flex-col md:flex-row md:items-end justify-between gap-4 mt-4">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface mb-2">User Orders Queue</h1>
                <p class="text-body-md text-on-surface-variant max-w-2xl">Manage and track customer purchases, gear rentals, and merchandise sales in real-time.</p>
            </div>
            
            <div class="flex gap-4">
                <div class="bg-surface-container-lowest border border-outline-variant kinetic-shadow p-4 rounded-xl flex items-center gap-4 min-w-[160px]">
                    <div class="w-12 h-12 rounded-full bg-error-container/20 flex items-center justify-center text-error border border-error/20">
                        <span class="material-symbols-outlined">pending_actions</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-outline">Pending</p>
                        <p class="text-headline-md font-bold text-on-surface"><?php echo number_format($pending_count); ?></p>
                    </div>
                </div>
                <div class="bg-surface-container-lowest border border-outline-variant kinetic-shadow p-4 rounded-xl flex items-center gap-4 min-w-[160px]">
                    <div class="w-12 h-12 rounded-full bg-primary-container/20 flex items-center justify-center text-primary border border-primary/20">
                        <span class="material-symbols-outlined">autorenew</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-outline">Completed</p>
                        <p class="text-headline-md font-bold text-on-surface"><?php echo number_format($processing_count); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl kinetic-shadow overflow-hidden flex flex-col">
            
            <div class="px-gutter py-4 border-b border-outline-variant flex flex-wrap items-center justify-between gap-4 bg-surface-bright">
                <div class="relative w-full md:w-96">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
                    <input id="searchInput" class="w-full pl-10 pr-4 py-2.5 bg-surface border border-outline-variant/50 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary text-sm outline-none transition-all" placeholder="Search orders by ID or Customer..." type="text">
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <button class="flex-1 md:flex-none px-4 py-2 bg-surface border border-outline-variant/50 text-on-surface-variant rounded-lg text-label-md font-bold hover:bg-surface-container transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">filter_list</span> Filter
                    </button>
                    <button class="flex-1 md:flex-none px-4 py-2 bg-primary text-white rounded-lg text-label-md font-bold hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 shadow-sm">
                        <span class="material-symbols-outlined text-[20px]">download</span> Export
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto flex-grow">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-surface-container-low text-on-surface-variant border-b border-outline-variant/50">
                        <tr>
                            <th class="px-6 py-4 font-bold text-[10px] uppercase tracking-widest">Order ID</th>
                            <th class="px-6 py-4 font-bold text-[10px] uppercase tracking-widest">Customer Name</th>
                            <th class="px-6 py-4 font-bold text-[10px] uppercase tracking-widest">Date</th>
                            <th class="px-6 py-4 font-bold text-[10px] uppercase tracking-widest text-right">Total Price</th>
                            <th class="px-6 py-4 font-bold text-[10px] uppercase tracking-widest text-center">Status</th>
                            <th class="px-6 py-4 font-bold text-[10px] uppercase tracking-widest text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/30">
                        <?php if(empty($all_orders)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-outline italic">No orders found in the system.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_orders as $order): 
                                // Determine styling based on status
                                $status = $order['status'];
                                $is_disabled = ($status == 'Completed' || $status == 'Cancelled');
                                
                                if ($status == 'Pending') {
                                    $status_class = 'status-pending border border-yellow-500/30';
                                } elseif ($status == 'Completed' || $status == 'Confirmed' || $status == 'Delivered') {
                                    $status_class = 'status-completed border border-green-500/30';
                                    $status = 'Completed'; // Standardize display text
                                } else {
                                    $status_class = 'status-cancelled border border-red-500/30';
                                    $status = 'Cancelled';
                                }
                            ?>
                            <tr class="hover:bg-surface-bright transition-colors group">
                                <td class="px-6 py-4 text-sm font-bold font-mono text-primary">#ORD-<?php echo str_pad($order['order_id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-surface-container-highest text-primary flex items-center justify-center text-xs font-bold border border-outline-variant/50">
                                            <?php 
                                            $words = explode(" ", trim($order['full_name']));
                                            echo strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                                            ?>
                                        </div>
                                        <span class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($order['full_name']); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-on-surface-variant font-medium"><?php echo date("M d, Y", strtotime($order['order_date'])); ?></td>
                                <td class="px-6 py-4 font-bold text-sm text-right text-on-surface">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="<?php echo $status_class; ?> px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-center gap-2">
                                        <button class="p-2 rounded-lg transition-colors <?php echo $is_disabled ? 'text-outline opacity-30 cursor-not-allowed' : 'text-green-600 hover:bg-green-50'; ?>" 
                                                <?php echo $is_disabled ? 'disabled' : "onclick=\"updateOrderStatus({$order['order_id']}, 'Completed')\""; ?> 
                                                title="Mark as Paid">
                                            <span class="material-symbols-outlined text-[20px]">check_circle</span>
                                        </button>
                                        <button class="p-2 rounded-lg transition-colors <?php echo $is_disabled ? 'text-outline opacity-30 cursor-not-allowed' : 'text-red-600 hover:bg-red-50'; ?>" 
                                                <?php echo $is_disabled ? 'disabled' : "onclick=\"updateOrderStatus({$order['order_id']}, 'Cancelled')\""; ?> 
                                                title="Cancel Order">
                                            <span class="material-symbols-outlined text-[20px]">cancel</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-margin-md grid grid-cols-1 lg:grid-cols-3 gap-gutter">
            <div class="lg:col-span-2 bg-surface-container-lowest border border-outline-variant rounded-xl p-gutter relative overflow-hidden group shadow-sm">
                <div class="relative z-10">
                    <h3 class="text-headline-md font-bold mb-4 text-on-surface">Inventory Insights</h3>
                    <div class="flex items-end gap-2 h-32">
                        <div class="flex-1 bg-primary rounded-t-lg transition-all group-hover:h-[80%] h-[60%]"></div>
                        <div class="flex-1 bg-primary/60 rounded-t-lg transition-all group-hover:h-[95%] h-[75%]"></div>
                        <div class="flex-1 bg-primary rounded-t-lg transition-all group-hover:h-[65%] h-[45%]"></div>
                        <div class="flex-1 bg-primary/40 rounded-t-lg transition-all group-hover:h-[40%] h-[30%]"></div>
                        <div class="flex-1 bg-primary rounded-t-lg transition-all group-hover:h-[70%] h-[55%]"></div>
                        <div class="flex-1 bg-primary/70 rounded-t-lg transition-all group-hover:h-[85%] h-[65%]"></div>
                    </div>
                    <p class="mt-4 text-sm text-on-surface-variant font-medium">Sales velocity is up 14% compared to last week. Keep an eye on pending orders.</p>
                </div>
                <div class="absolute -right-16 -top-16 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-colors pointer-events-none"></div>
            </div>
            
            <div class="bg-primary text-white rounded-xl p-gutter shadow-lg relative overflow-hidden flex flex-col justify-between">
                <div class="relative z-10">
                    <span class="material-symbols-outlined text-[48px] opacity-50 mb-4">bolt</span>
                    <h3 class="text-xl font-bold mb-2">AI Quick Action</h3>
                    <p class="text-sm text-white/80 leading-relaxed">Let ShuttleSync AI optimize your inventory and alert you of stock anomalies based on historical order patterns.</p>
                </div>
                <button class="relative z-10 mt-6 w-full py-3 bg-white text-primary font-bold rounded-lg hover:bg-surface-bright active:scale-95 transition-all text-sm shadow-md">
                    Run Optimization
                </button>
                <div class="absolute inset-0 bg-gradient-to-br from-primary via-primary to-primary-container opacity-50 pointer-events-none"></div>
            </div>
        </div>
        
        <footer class="mt-margin-md pt-8 border-t border-outline-variant flex justify-between text-[12px] font-bold tracking-widest text-outline uppercase pb-8">
            <p>© 2024 ShuttleSync Technologies.</p>
            <div class="flex gap-6">
                <a class="hover:text-primary transition-colors" href="#">System Status</a>
                <a class="hover:text-primary transition-colors" href="#">Support</a>
            </div>
        </footer>
    </main>
</div>

<script>
    // Handle status updates via hidden form
    function updateOrderStatus(orderId, newStatus) {
        if(confirm('Are you sure you want to mark Order #ORD-' + String(orderId).padStart(5, '0') + ' as ' + newStatus + '?')) {
            document.getElementById('action_order_id').value = orderId;
            document.getElementById('action_status').value = newStatus;
            document.getElementById('orderActionForm').submit();
        }
    }

    // Search Filter Logic
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                if (row.children.length > 1) { // Skip the "No orders found" row if present
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                }
            });
        });

        // Auto-hide alerts
        const alerts = document.querySelectorAll('.bg-primary-container\\/20, .bg-error-container\\/20');
        if (alerts.length > 0) {
            setTimeout(() => {
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        }
        
        // Add entrance animation to rows
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(10px)';
            setTimeout(() => {
                row.style.transition = 'all 0.4s cubic-bezier(0.16, 1, 0.3, 1)';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, index * 50);
        });
    });
</script>

</body>
</html>