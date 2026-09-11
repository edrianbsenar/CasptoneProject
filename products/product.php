<?php
session_start();
require_once __DIR__ . '/../includes/database_connect.php';
require_once __DIR__ . '/../includes/config.php';

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$success_message = '';
$error_message = '';

if (!isset($_GET['id']) || empty($_GET['id'])) { header("Location: ../ecommerce.php"); exit; }
$product_id = (int)$_GET['id'];

// Handle Add to Cart / Buy Now
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $quantity = max(1, (int)$_POST['quantity']);
    $custom_string = null;
    if (isset($_POST['variant'])) {
        $custom_string = "Variant: " . trim($_POST['variant']);
        if (!empty($_POST['custom_name'])) {
            $custom_string .= " | Name: " . strtoupper(htmlspecialchars(trim($_POST['custom_name'])));
        }
    }
    if ($is_logged_in) {
        try {
            $pdo->prepare("INSERT INTO Cart_Items (user_id, product_id, quantity, customization) VALUES (?, ?, ?, ?)")->execute([$user_id, $product_id, $quantity, $custom_string]);
        } catch (PDOException $e) { $error_message = "Could not add item to cart."; }
    } else {
        if (!isset($_SESSION['guest_cart_items'])) $_SESSION['guest_cart_items'] = [];
        $_SESSION['guest_cart_items'][] = ['product_id'=>$product_id, 'quantity'=>$quantity, 'customization'=>$custom_string];
    }
    if (empty($error_message)) {
        if ($_POST['action'] === 'buy_now') { header("Location: ../cart.php"); exit; }
        else { $success_message = "Item added to your cart!"; }
    }
}

// Fetch product
try {
    $stmt = $pdo->prepare("SELECT * FROM Products WHERE product_id = ? AND is_active = TRUE");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    if (!$product) { header("Location: ../ecommerce.php"); exit; }
} catch (PDOException $e) { header("Location: ../ecommerce.php"); exit; }

$is_out_of_stock = $product['stock_quantity'] <= 0;
$is_low_stock = $product['stock_quantity'] > 0 && $product['stock_quantity'] <= 5;
$max_stock = max(1, (int)$product['stock_quantity']);

// Parse images
$images = [];
$decoded = json_decode($product['image_url'], true);
if (is_array($decoded) && count($decoded) > 0) { $images = $decoded; }
elseif (!empty($product['image_url'])) { $images[] = $product['image_url']; }
else { $images[] = "https://picsum.photos/seed/prod_".$product_id."/800/800"; }

// Related products (same category)
$related = [];
try {
    $rstmt = $pdo->prepare("SELECT * FROM Products WHERE category = ? AND product_id != ? AND is_active = TRUE ORDER BY RAND() LIMIT 4");
    $rstmt->execute([$product['category'], $product_id]);
    $related = $rstmt->fetchAll();
} catch (Exception $e) {}

// Cart count
$cart_count = 0;
if ($is_logged_in) {
    try { $cs = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM Cart_Items WHERE user_id = ?"); $cs->execute([$user_id]); $cart_count = (int)$cs->fetchColumn(); } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?php echo htmlspecialchars($product['name']); ?> | ShuttleSync Shop</title>
    <?php include __DIR__ . '/../includes/tailwind_config.php'; ?>
    <style>
        @keyframes fadeSlideUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
        .anim-in{animation:fadeSlideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
        .anim-d1{animation-delay:.05s;opacity:0;}
        .anim-d2{animation-delay:.1s;opacity:0;}
        .anim-d3{animation-delay:.15s;opacity:0;}
        .variant-radio:checked + label{border-color:#3145e6;background:#3145e6;color:#fff;}
        .thumb-btn{border:2px solid transparent;transition:all .2s;}
        .thumb-btn.active{border-color:#3145e6;}
        .thumb-btn:hover{border-color:rgba(49,69,230,0.3);}
        .main-img-wrap{background:#f8f9fc;border-radius:20px;position:relative;overflow:hidden;}
        .main-img-wrap img{transition:opacity .3s;}
        .related-card{background:#fff;border-radius:16px;border:1px solid rgba(0,0,0,0.06);overflow:hidden;transition:all .3s;box-shadow:0 2px 8px rgba(0,0,0,0.03);}
        .related-card:hover{box-shadow:0 8px 30px rgba(0,0,0,0.08);transform:translateY(-2px);}
        .stock-bar{height:6px;border-radius:3px;background:#e8eaf0;overflow:hidden;}
        .stock-bar-fill{height:100%;border-radius:3px;transition:width .5s ease;}
        @keyframes cartBounce{0%,100%{transform:scale(1);}50%{transform:scale(1.15);}}
        .cart-bounce{animation:cartBounce .3s ease;}
    </style>
</head>
<body class="bg-surface min-h-screen antialiased">

<?php include __DIR__ . '/../includes/nav_bar.php'; ?>

<main class="max-w-6xl mx-auto w-full px-4 md:px-8 pt-24 pb-20">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-xs font-medium text-on-surface-variant/60 mb-6 anim-in">
        <a href="../index.php" class="hover:text-primary transition-colors">Home</a>
        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
        <a href="../ecommerce.php" class="hover:text-primary transition-colors">Shop</a>
        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
        <a href="../ecommerce.php?category=<?php echo urlencode($product['category']); ?>" class="hover:text-primary transition-colors"><?php echo htmlspecialchars($product['category']); ?></a>
        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
        <span class="text-on-surface font-semibold truncate max-w-[200px]"><?php echo htmlspecialchars($product['name']); ?></span>
    </nav>

    <?php if (!empty($success_message)): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-2xl flex items-center gap-3 text-green-700 anim-in" id="successAlert">
            <span class="material-symbols-outlined text-lg">check_circle</span>
            <p class="font-bold text-sm flex-1"><?php echo $success_message; ?></p>
            <a href="../cart.php" class="text-xs font-bold text-primary hover:underline">View Cart →</a>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl flex items-center gap-3 text-accent anim-in">
            <span class="material-symbols-outlined text-lg">error</span>
            <p class="font-bold text-sm"><?php echo $error_message; ?></p>
        </div>
    <?php endif; ?>

    <!-- Product Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">

        <!-- Image Gallery -->
        <div class="anim-in anim-d1">
            <div class="main-img-wrap aspect-square flex items-center justify-center p-6 mb-4">
                <?php if ($is_out_of_stock): ?>
                    <div class="absolute top-4 left-4 z-10 bg-gray-800 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Out of Stock</div>
                <?php endif; ?>
                <?php if ($is_low_stock): ?>
                    <div class="absolute top-4 left-4 z-10 bg-amber-500 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Only <?php echo $product['stock_quantity']; ?> Left</div>
                <?php endif; ?>
                <img id="mainImage" src="<?php echo (strpos($images[0], 'http') === 0) ? $images[0] : '../' . htmlspecialchars($images[0]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="max-w-full max-h-full object-contain mix-blend-multiply">
            </div>
            <?php if (count($images) > 1): ?>
            <div class="flex gap-2 overflow-x-auto pb-1">
                <?php foreach ($images as $idx => $img_path):
                    $fp = (strpos($img_path, 'http') === 0) ? $img_path : '../' . htmlspecialchars($img_path);
                ?>
                <button type="button" onclick="swapImage('<?php echo $fp; ?>', this)" class="thumb-btn shrink-0 w-16 h-16 rounded-xl bg-white border-2 <?php echo $idx===0 ? 'active border-primary' : 'border-outline-variant/20'; ?> overflow-hidden p-1">
                    <img src="<?php echo $fp; ?>" class="w-full h-full object-contain mix-blend-multiply">
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Product Info -->
        <div class="flex flex-col anim-in anim-d2">
            <div class="mb-1">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-primary/8 text-primary rounded-md text-[10px] font-bold uppercase tracking-widest"><?php echo htmlspecialchars($product['category']); ?></span>
            </div>

            <h1 class="text-2xl md:text-3xl font-bold text-on-surface mb-3 leading-tight"><?php echo htmlspecialchars($product['name']); ?></h1>

            <!-- Price -->
            <div class="flex items-baseline gap-3 mb-4">
                <span class="text-3xl font-bold text-primary">₱<?php echo number_format($product['price'], 2); ?></span>
            </div>

            <!-- Stock Bar -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-bold text-on-surface-variant">Availability</span>
                    <?php if ($is_out_of_stock): ?>
                        <span class="text-xs font-bold text-accent">Out of Stock</span>
                    <?php elseif ($is_low_stock): ?>
                        <span class="text-xs font-bold text-amber-600">Only <?php echo $product['stock_quantity']; ?> remaining</span>
                    <?php else: ?>
                        <span class="text-xs font-bold text-green-600">In Stock (<?php echo $product['stock_quantity']; ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="stock-bar">
                    <div class="stock-bar-fill <?php echo $is_out_of_stock ? 'bg-accent' : ($is_low_stock ? 'bg-amber-500' : 'bg-green-500'); ?>" style="width:<?php echo min(100, ($product['stock_quantity'] / 50) * 100); ?>%"></div>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="grid grid-cols-2 gap-3 mb-6">
                <div class="bg-surface rounded-xl p-3 flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-primary text-lg mt-0.5">storefront</span>
                    <div>
                        <p class="text-[11px] font-bold text-on-surface">Pickup Only</p>
                        <p class="text-[10px] text-on-surface-variant">Collect at facility</p>
                    </div>
                </div>
                <div class="bg-surface rounded-xl p-3 flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-primary text-lg mt-0.5">payments</span>
                    <div>
                        <p class="text-[11px] font-bold text-on-surface">Flexible Payment</p>
                        <p class="text-[10px] text-on-surface-variant">GCash, PayPal, Card</p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form method="POST" action="product.php?id=<?php echo $product_id; ?>" class="space-y-5 flex-grow flex flex-col justify-end">

                <?php if ($product['category'] == 'Jersey' || stripos($product['category'], 'shoe') !== false): ?>
                <div>
                    <label class="text-[11px] font-bold text-on-surface uppercase tracking-widest mb-2.5 block">Select Size</label>
                    <div class="flex flex-wrap gap-2">
                        <?php
                        $sizes = ($product['category'] == 'Jersey') ? ['S','M','L','XL'] : ['US 7','US 8','US 9','US 10','US 11'];
                        foreach ($sizes as $i => $size): ?>
                        <div>
                            <input type="radio" id="sz<?php echo $i; ?>" name="variant" value="<?php echo $size; ?>" class="sr-only variant-radio" required <?php echo $i===0?'checked':''; ?>>
                            <label for="sz<?php echo $i; ?>" class="w-14 h-11 flex items-center justify-center rounded-xl border-2 border-outline-variant/30 text-sm font-bold cursor-pointer hover:border-primary/50 transition-all"><?php echo $size; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($product['category'] == 'Jersey'): ?>
                    <input type="text" name="custom_name" placeholder="Custom back name (optional, max 15 chars)" maxlength="15" class="mt-3 w-full bg-surface border border-outline-variant/30 rounded-xl px-4 py-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 outline-none transition-all uppercase placeholder:normal-case">
                    <?php endif; ?>
                </div>
                <?php elseif ($product['category'] == 'Shuttlecock' || $product['category'] == 'Shuttlecocks'): ?>
                <div>
                    <label class="text-[11px] font-bold text-on-surface uppercase tracking-widest mb-2.5 block">Select Speed</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach (['Speed 76','Speed 77','Tournament'] as $i => $speed): ?>
                        <div>
                            <input type="radio" id="sp<?php echo $i; ?>" name="variant" value="<?php echo $speed; ?>" class="sr-only variant-radio" required <?php echo $i===0?'checked':''; ?>>
                            <label for="sp<?php echo $i; ?>" class="px-5 h-11 flex items-center justify-center rounded-xl border-2 border-outline-variant/30 text-sm font-bold cursor-pointer hover:border-primary/50 transition-all whitespace-nowrap"><?php echo $speed; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quantity + Buttons -->
                <div class="pt-4 border-t border-outline-variant/20 space-y-3">
                    <?php if (!$is_out_of_stock): ?>
                    <div>
                        <label class="text-[11px] font-bold text-on-surface uppercase tracking-widest mb-2 block">Quantity</label>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center bg-surface border border-outline-variant/30 rounded-xl overflow-hidden">
                                <button type="button" onclick="updateQty(-1)" class="w-10 h-10 flex items-center justify-center text-on-surface-variant hover:text-primary transition" id="btnMinus" disabled>
                                    <span class="material-symbols-outlined text-lg">remove</span>
                                </button>
                                <input type="number" id="qtyInput" name="quantity" value="1" min="1" max="<?php echo $max_stock; ?>" class="w-12 text-center bg-transparent border-none text-sm font-bold focus:ring-0 p-0" readonly>
                                <button type="button" onclick="updateQty(1)" class="w-10 h-10 flex items-center justify-center text-on-surface-variant hover:text-primary transition" id="btnPlus">
                                    <span class="material-symbols-outlined text-lg">add</span>
                                </button>
                            </div>
                            <span class="text-[11px] text-on-surface-variant/50 font-medium"><?php echo $max_stock; ?> available</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="flex gap-3">
                        <?php if ($is_out_of_stock): ?>
                            <button type="button" disabled class="flex-1 h-12 bg-surface border border-outline-variant/30 text-on-surface-variant/50 rounded-xl font-bold cursor-not-allowed">Out of Stock</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="add_to_cart" class="flex-1 h-12 bg-surface border-2 border-primary/20 text-primary rounded-xl font-bold text-sm hover:bg-primary hover:text-white active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span> Add to Cart
                            </button>
                            <button type="submit" name="action" value="buy_now" class="flex-1 h-12 bg-gradient-to-r from-primary to-primary-dark text-white rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:shadow-xl active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[18px]">bolt</span> Buy Now
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Description -->
    <div class="mt-12 bg-white rounded-2xl border border-outline-variant/10 p-6 md:p-8 shadow-sm anim-in anim-d3">
        <h3 class="text-base font-bold text-on-surface mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-lg">description</span> Product Description
        </h3>
        <div class="text-sm text-on-surface-variant leading-relaxed max-w-4xl">
            <?php echo nl2br(htmlspecialchars($product['description'] ?: 'High quality badminton gear designed for professional performance and durability on the court.')); ?>
        </div>
    </div>

    <!-- Related Products -->
    <?php if (count($related) > 0): ?>
    <div class="mt-12 anim-in anim-d4">
        <div class="flex items-center justify-between mb-5">
            <div>
                <span class="text-accent text-[11px] font-bold tracking-[0.25em] uppercase mb-1 block">You May Also Like</span>
                <h2 class="text-xl font-bold">Related Products</h2>
            </div>
            <a href="../ecommerce.php?category=<?php echo urlencode($product['category']); ?>" class="text-primary text-xs font-bold hover:underline">View All →</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($related as $rp):
                $ri = json_decode($rp['image_url'], true);
                $rimg = (is_array($ri) && count($ri) > 0) ? $ri[0] : ($rp['image_url'] ?? '');
                if (!empty($rimg) && file_exists($rimg)) { $rimg_url = htmlspecialchars($rimg); }
                else { $rimg_url = "https://picsum.photos/seed/prod_".$rp['product_id']."/400/400"; }
                $r_out = $rp['stock_quantity'] <= 0;
            ?>
            <a href="product.php?id=<?php echo $rp['product_id']; ?>" class="related-card group">
                <div class="aspect-square bg-surface/50 flex items-center justify-center p-4 relative">
                    <img loading="lazy" class="w-full h-full object-contain mix-blend-multiply group-hover:scale-105 transition-transform duration-500" src="<?php echo $rimg_url; ?>" alt="<?php echo htmlspecialchars($rp['name']); ?>">
                    <?php if ($r_out): ?>
                        <div class="absolute inset-0 bg-white/60 flex items-center justify-center"><span class="bg-gray-800 text-white text-[9px] font-bold px-3 py-1 rounded uppercase">Out of Stock</span></div>
                    <?php endif; ?>
                </div>
                <div class="p-3">
                    <p class="text-xs font-bold text-on-surface line-clamp-2 mb-1"><?php echo htmlspecialchars($rp['name']); ?></p>
                    <p class="text-sm font-bold text-primary">₱<?php echo number_format($rp['price'], 2); ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/mobile_nav.php'; ?>

<script>
const maxStock = <?php echo $max_stock; ?>;
const qtyInput = document.getElementById('qtyInput');
const btnMinus = document.getElementById('btnMinus');
const btnPlus = document.getElementById('btnPlus');

function updateQty(change) {
    let v = parseInt(qtyInput.value) + change;
    if (v >= 1 && v <= maxStock) qtyInput.value = v;
    if (btnMinus) btnMinus.disabled = qtyInput.value <= 1;
    if (btnPlus) btnPlus.disabled = qtyInput.value >= maxStock;
}

function swapImage(src, btn) {
    const main = document.getElementById('mainImage');
    main.style.opacity = '0.5';
    setTimeout(() => { main.src = src; main.style.opacity = '1'; }, 150);
    document.querySelectorAll('.thumb-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// Auto-hide success alert
setTimeout(() => {
    const a = document.getElementById('successAlert');
    if (a) { a.style.opacity = '0'; a.style.transition = 'opacity .5s'; setTimeout(() => a.remove(), 500); }
}, 4000);
</script>

</body>
</html>
