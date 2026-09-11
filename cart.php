<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database_connect.php';

Config::load();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: /authentication/login");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

if (isset($_GET['payment']) && $_GET['payment'] === 'success') {
    $success_message = htmlspecialchars($_GET['message'] ?? 'Payment successful! Your order has been confirmed.');
}

// ==========================================
// 1. PROCESS CART ACTIONS & CHECKOUT (POST)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action == 'remove_item') {
            $cart_item_id = (int)$_POST['cart_item_id'];
            $stmt = $pdo->prepare("DELETE FROM Cart_Items WHERE cart_item_id = ? AND user_id = ?");
            $stmt->execute([$cart_item_id, $user_id]);
            $success_message = "Item removed from cart.";
        }
        elseif ($action == 'remove_booking') {
            $booking_id = (int)$_POST['booking_id'];
            $stmt = $pdo->prepare("DELETE FROM Bookings WHERE booking_id = ? AND user_id = ? AND status = 'In Cart'");
            $stmt->execute([$booking_id, $user_id]);
            $success_message = "Court booking removed from cart.";
        }
        elseif ($action == 'checkout') {
            $payment_method = $_POST['payment_method'];
            
            if ($payment_method == 'cash') {
                $pdo->beginTransaction();
                
                $update_bookings = $pdo->prepare("UPDATE Bookings SET status = 'Pending' WHERE user_id = ? AND status = 'In Cart'");
                $update_bookings->execute([$user_id]);
                
                $cart_items_stmt = $pdo->prepare("SELECT ci.quantity, p.price FROM Cart_Items ci JOIN Products p ON ci.product_id = p.product_id WHERE ci.user_id = ?");
                $cart_items_stmt->execute([$user_id]);
                $physical_items = $cart_items_stmt->fetchAll();
                
                if (count($physical_items) > 0) {
                    $order_total = 0;
                    foreach($physical_items as $item) {
                        $order_total += $item['price'] * $item['quantity'];
                    }
                    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                    $insert_order = $pdo->prepare("INSERT INTO Orders (order_number, user_id, order_date, total_amount, status, payment_method) VALUES (?, ?, CURRENT_DATE, ?, 'Pending', 'cash')");
                    $insert_order->execute([$order_number, $user_id, $order_total]);
                    $pdo->prepare("DELETE FROM Cart_Items WHERE user_id = ?")->execute([$user_id]);
                }
                
                $pdo->commit();
                $success_message = "Success! Please pay at the counter within 24 hours to secure your reservations.";
            } else {
                $error_message = "Please use the payment buttons above to complete your purchase.";
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// ==========================================
// 2. FETCH CART DATA (Products + Bookings)
// ==========================================
$stmt = $pdo->prepare("SELECT ci.cart_item_id, ci.quantity, ci.customization, p.product_id, p.name, p.price, p.category, p.image_url FROM Cart_Items ci JOIN Products p ON ci.product_id = p.product_id WHERE ci.user_id = ? ORDER BY ci.added_at DESC");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

$book_stmt = $pdo->prepare("SELECT b.booking_id, b.booking_reference, b.booking_date, b.start_time, b.end_time, b.total_price, c.name as court_name FROM Bookings b JOIN Courts c ON b.court_id = c.court_id WHERE b.user_id = ? AND b.status = 'In Cart'");
$book_stmt->execute([$user_id]);
$cart_bookings = $book_stmt->fetchAll();

// ==========================================
// 3. CALCULATE TOTALS
// ==========================================
$subtotal = 0;
foreach ($cart_items as $item) { $subtotal += ($item['price'] * $item['quantity']); }
foreach ($cart_bookings as $booking) { $subtotal += $booking['total_price']; }

$tax_rate = 0.08; 
$tax_amount = $subtotal * $tax_rate;
$grand_total = $subtotal + $tax_amount;

$is_cart_empty = (empty($cart_items) && empty($cart_bookings));
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Secure Checkout | ShuttleSync</title>
    <?php include __DIR__ . '/includes/tailwind_config.php'; ?>
    <style>
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-slide-in { animation: slideIn 0.4s ease-out forwards; }
        .payment-btn { transition: all 0.2s ease; }
        .payment-btn:hover { transform: translateY(-1px); }
        .payment-btn:active { transform: scale(0.98); }
        .item-card { transition: all 0.2s ease; }
        .item-card:hover { border-color: rgba(49,69,230,0.3); }
    </style>
</head>
<body class="bg-background text-on-background min-h-screen flex flex-col pb-20 md:pb-0">

<form id="removeForm" method="POST" action="cart" style="display: none;">
    <input type="hidden" name="action" id="removeAction">
    <input type="hidden" name="cart_item_id" id="removeCartItemId">
    <input type="hidden" name="booking_id" id="removeBookingId">
</form>

<?php include __DIR__ . '/includes/nav_bar.php'; ?>

<main class="flex-grow max-w-7xl mx-auto px-4 md:px-8 py-8 pt-24 w-full">
    
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-[28px]">shopping_cart_checkout</span>
            </div>
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-on-surface tracking-tight">Checkout</h1>
                <p class="text-on-surface-variant text-sm font-medium">Review your items and complete payment</p>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl flex items-center gap-3 text-green-800 animate-slide-in">
            <span class="material-symbols-outlined text-green-600">check_circle</span>
            <p class="font-bold text-sm"><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="mb-6 p-4 bg-error-container border border-error/50 rounded-xl flex items-center gap-3 text-error animate-slide-in">
            <span class="material-symbols-outlined">error</span>
            <p class="font-bold text-sm"><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$is_cart_empty): ?>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left: Cart Items -->
        <div class="lg:col-span-8 space-y-4">
            
            <!-- Court Bookings -->
            <?php if (!empty($cart_bookings)): ?>
            <div class="mb-2">
                <h2 class="text-sm font-bold text-outline uppercase tracking-widest flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-[18px]">stadium</span> Court Reservations (<?php echo count($cart_bookings); ?>)
                </h2>
            </div>
            <?php foreach ($cart_bookings as $booking): ?>
            <div class="item-card bg-surface-container-lowest p-5 rounded-2xl border border-outline-variant shadow-kinetic flex flex-col sm:flex-row gap-4 items-center relative overflow-hidden">
                <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary"></div>
                <div class="w-14 h-14 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0 ml-2">
                    <span class="material-symbols-outlined text-[28px]">stadium</span>
                </div>
                <div class="flex-grow text-center sm:text-left w-full">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                        <div>
                            <h3 class="text-lg font-bold text-on-surface leading-tight"><?php echo htmlspecialchars($booking['court_name']); ?></h3>
                            <p class="text-sm text-on-surface-variant font-medium mt-0.5">
                                <span class="material-symbols-outlined text-[14px] align-middle mr-1">calendar_today</span>
                                <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                                <span class="mx-1">&middot;</span>
                                <span class="material-symbols-outlined text-[14px] align-middle mr-1">schedule</span>
                                <?php echo date('h:i A', strtotime($booking['start_time'])) . ' - ' . date('h:i A', strtotime($booking['end_time'])); ?>
                            </p>
                        </div>
                        <span class="text-xl font-bold text-primary"><?php echo '&#8369;' . number_format($booking['total_price'], 2); ?></span>
                    </div>
                    <div class="mt-3 flex justify-center sm:justify-end border-t border-outline-variant/30 pt-3">
                        <button type="button" onclick="removeBooking(<?php echo $booking['booking_id']; ?>)" class="text-xs font-bold tracking-widest uppercase text-error hover:text-red-700 transition-colors flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">delete</span> Remove
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Products -->
            <?php if (!empty($cart_items)): ?>
            <div class="mt-6 mb-2">
                <h2 class="text-sm font-bold text-outline uppercase tracking-widest flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-[18px]">shopping_bag</span> Gear &amp; Equipment (<?php echo count($cart_items); ?>)
                </h2>
            </div>
            <?php foreach ($cart_items as $item): 
                $img_url = (!empty($item['image_url'])) ? htmlspecialchars($item['image_url']) : "https://picsum.photos/seed/shuttlesync_prod_" . $item['product_id'] . "/300/300";
            ?>
            <div class="item-card bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant shadow-kinetic flex flex-col sm:flex-row gap-4 items-center">
                <div class="w-20 h-20 sm:w-24 sm:h-24 flex-shrink-0 bg-surface-variant rounded-xl overflow-hidden border border-outline-variant/30">
                    <img alt="Product" class="w-full h-full object-cover" src="<?php echo $img_url; ?>"/>
                </div>
                <div class="flex-grow text-center sm:text-left w-full">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                        <div>
                            <h3 class="text-base font-bold text-on-surface leading-tight"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <?php if (!empty($item['customization'])): ?>
                                <p class="text-[10px] text-primary mt-1 font-bold tracking-widest uppercase bg-primary/5 inline-block px-2 py-0.5 rounded border border-primary/10"><?php echo htmlspecialchars($item['customization']); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-outline mt-1"><?php echo htmlspecialchars($item['category']); ?></p>
                        </div>
                        <div class="text-right">
                            <span class="text-lg font-bold text-primary"><?php echo '&#8369;' . number_format($item['price'] * $item['quantity'], 2); ?></span>
                            <p class="text-xs text-outline"><?php echo '&#8369;' . number_format($item['price'], 2); ?> each &times; <?php echo $item['quantity']; ?></p>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-center sm:justify-end border-t border-outline-variant/30 pt-3">
                        <button type="button" onclick="removeProduct(<?php echo $item['cart_item_id']; ?>)" class="text-xs font-bold tracking-widest uppercase text-error hover:text-red-700 transition-colors flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">delete</span> Remove
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

        </div>

        <!-- Right: Order Summary & Payment -->
        <div class="lg:col-span-4">
            <div class="bg-surface-container-lowest p-6 rounded-2xl border border-outline-variant shadow-kinetic sticky top-24">
                
                <h2 class="text-lg font-bold text-on-surface mb-5 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[22px]">receipt_long</span> Order Summary
                </h2>
                
                <div class="space-y-3 border-b border-outline-variant/50 pb-5 mb-5">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-on-surface-variant">Subtotal</span>
                        <span class="text-sm font-bold text-on-surface"><?php echo '&#8369;' . number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-on-surface-variant">Tax (8%)</span>
                        <span class="text-sm font-bold text-on-surface"><?php echo '&#8369;' . number_format($tax_amount, 2); ?></span>
                    </div>
                    <div class="flex justify-between items-end pt-2">
                        <span class="text-base font-bold text-on-surface">Total Due</span>
                        <span class="text-2xl font-bold text-primary"><?php echo '&#8369;' . number_format($grand_total, 2); ?></span>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold tracking-widest text-outline uppercase">Payment Method</h3>
                    
                    <?php if (!$is_cart_empty): ?>
                    <!-- PayPal -->
                    <div id="paypal-button-container" class="w-full"></div>
                    
                    <!-- GCash -->
                    <button type="button" onclick="initPayMongo('gcash')" id="gcashBtn" class="payment-btn w-full py-3 bg-[#007DFB] text-white font-bold text-sm rounded-xl flex items-center justify-center gap-2 shadow-md">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/><circle cx="12" cy="12" r="5"/></svg>
                        Pay with GCash
                    </button>

                    <!-- Pay at Counter -->
                    <div class="border-t border-outline-variant/50 pt-3 mt-3">
                        <form method="POST" action="cart" id="cashForm">
                            <input type="hidden" name="action" value="checkout">
                            <input type="hidden" name="payment_method" value="cash">
                            <button type="submit" <?php echo $is_cart_empty ? 'disabled' : ''; ?> class="payment-btn w-full py-3 bg-surface-container text-on-surface font-bold text-sm rounded-xl flex items-center justify-center gap-2 border border-outline-variant hover:bg-surface-container-high disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="material-symbols-outlined text-[18px]">storefront</span>
                                Pay at Counter
                            </button>
                        </form>
                        <div class="mt-3 bg-amber-50 border border-amber-200 p-3 rounded-xl">
                            <p class="text-[10px] text-amber-700 font-bold tracking-widest uppercase mb-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">warning</span> Pay at Counter Policy
                            </p>
                            <p class="text-xs text-amber-800 leading-relaxed">Your order will be marked <span class="font-bold">Pending</span>. Pay at the front desk within 24 hours to confirm.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Trust Bar -->
                <div class="mt-6 flex justify-center gap-5 opacity-40">
                    <span class="material-symbols-outlined text-xl" title="Secure Payment">verified_user</span>
                    <span class="material-symbols-outlined text-xl" title="Fast Processing">bolt</span>
                    <span class="material-symbols-outlined text-xl" title="Data Encrypted">lock</span>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Empty Cart -->
    <div class="bg-surface-container-lowest rounded-2xl border border-dashed border-outline-variant/60 p-16 flex flex-col items-center justify-center text-center">
        <div class="w-20 h-20 rounded-full bg-surface-container flex items-center justify-center mb-5">
            <span class="material-symbols-outlined text-5xl text-outline/50">shopping_cart</span>
        </div>
        <h2 class="text-2xl font-bold text-on-surface mb-2">Your cart is empty</h2>
        <p class="text-on-surface-variant mb-8 max-w-md">Add court reservations or gear to get started with your order.</p>
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="ecommerce" class="btn-primary px-6 py-3 rounded-xl font-bold flex items-center justify-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-[20px]">shopping_bag</span> Shop Gear
            </a>
            <a href="courtbooking" class="bg-surface-container-highest text-on-surface-variant px-6 py-3 rounded-xl font-bold hover:bg-outline-variant/30 active:scale-95 transition-all flex items-center justify-center gap-2 border border-outline-variant">
                <span class="material-symbols-outlined text-[20px]">stadium</span> Book Court
            </a>
        </div>
    </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/mobile_nav.php'; ?>

<script>
    // Remove Items
    function removeProduct(cartItemId) {
        if (confirm("Remove this product from your cart?")) {
            document.getElementById('removeAction').value = 'remove_item';
            document.getElementById('removeCartItemId').value = cartItemId;
            document.getElementById('removeForm').submit();
        }
    }

    function removeBooking(bookingId) {
        if (confirm("Cancel this court reservation?")) {
            document.getElementById('removeAction').value = 'remove_booking';
            document.getElementById('removeBookingId').value = bookingId;
            document.getElementById('removeForm').submit();
        }
    }

    // ==========================================
    // PAYPAL INTEGRATION
    // ==========================================
    const PayPalClientId = '<?php echo htmlspecialchars(Config::get("PAYPAL_CLIENT_ID")); ?>';
    let paypalOrderId = null;
    let dbOrderId = null;

    if (PayPalClientId && PayPalClientId !== 'YOUR_PAYPAL_CLIENT_ID_HERE') {
        const script = document.createElement('script');
        script.src = 'https://www.paypal.com/sdk/js?client-id=' + PayPalClientId + '&currency=PHP&intent=capture&disable-funding=credit,card';
        script.onload = initPayPalButtons;
        document.head.appendChild(script);
    } else {
        const ppContainer = document.getElementById('paypal-button-container');
        if (ppContainer) ppContainer.innerHTML = '<p class="text-xs text-outline text-center py-2">PayPal not configured</p>';
    }

    function initPayPalButtons() {
        if (typeof paypal === 'undefined') return;

        paypal.Buttons({
            style: { layout: 'vertical', color: 'blue', shape: 'rect', label: 'pay', height: 50 },
            createOrder: function(data, actions) {
                return fetch('/payment/paypal_handler.php?action=create_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    paypalOrderId = data.order_id;
                    dbOrderId = data.db_order_id;
                    return data.order_id;
                });
            },
            onApprove: function(data, actions) {
                return fetch('/payment/paypal_handler.php?action=capture_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'order_id=' + encodeURIComponent(paypalOrderId) + '&db_order_id=' + encodeURIComponent(dbOrderId)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    window.location.href = '/cart?payment=success&message=' + encodeURIComponent('Payment successful! Transaction: ' + data.transaction_id);
                });
            },
            onError: function(err) { console.error('PayPal error:', err); alert('Payment failed. Please try again.'); },
            onCancel: function() { console.log('PayPal payment cancelled'); }
        }).render('#paypal-button-container');
    }

    // ==========================================
    // PAYMONGO INTEGRATION (GCash only)
    // ==========================================
    let paymongoCheckoutWindow = null;
    let pollInterval = null;

    function initPayMongo(method) {
        const btn = document.getElementById('gcashBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-[18px]">refresh</span> Processing...';
        }

        fetch('/payment/paymongo_handler.php?action=create_session', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'payment_method=' + encodeURIComponent(method)
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) throw new Error(data.error);
            
            const width = 500, height = 700;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            
            paymongoCheckoutWindow = window.open(
                data.checkout_url,
                'paymongo_checkout',
                'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
            );

            startPaymentPolling(data.db_order_id);
        })
        .catch(err => {
            console.error('PayMongo error:', err);
            alert('Failed to initiate payment: ' + err.message);
            resetGCashButton();
        });
    }

    function startPaymentPolling(orderId) {
        if (pollInterval) clearInterval(pollInterval);

        pollInterval = setInterval(() => {
            if (paymongoCheckoutWindow && paymongoCheckoutWindow.closed) {
                clearInterval(pollInterval);
                fetch('/payment/paymongo_handler.php?action=check_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'db_order_id=' + encodeURIComponent(orderId)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'Confirmed') {
                        window.location.href = '/cart?payment=success&message=' + encodeURIComponent('Payment successful!');
                    } else {
                        resetGCashButton();
                        alert('Payment was not completed. Please try again.');
                    }
                })
                .catch(() => { resetGCashButton(); });
                return;
            }

            fetch('/payment/paymongo_handler.php?action=check_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'db_order_id=' + encodeURIComponent(orderId)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'Confirmed') {
                    clearInterval(pollInterval);
                    if (paymongoCheckoutWindow && !paymongoCheckoutWindow.closed) paymongoCheckoutWindow.close();
                    window.location.href = '/cart?payment=success&message=' + encodeURIComponent('Payment successful!');
                }
            });
        }, 3000);

        setTimeout(() => { if (pollInterval) clearInterval(pollInterval); resetGCashButton(); }, 600000);
    }

    function resetGCashButton() {
        const btn = document.getElementById('gcashBtn');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/><circle cx="12" cy="12" r="5"/></svg> Pay with GCash';
        }
    }

    // Auto-hide alerts
    document.addEventListener('DOMContentLoaded', () => {
        const alerts = document.querySelectorAll('.animate-slide-in');
        if (alerts.length > 0) {
            setTimeout(() => {
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        }
    });
</script>

</body>
</html>
