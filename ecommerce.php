<?php
session_start();
require_once __DIR__ . '/includes/database_connect.php';

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$sql = "SELECT * FROM Products WHERE is_active = TRUE";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}
if (!empty($category_filter)) {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
}

switch ($sort) {
    case 'price_low': $sql .= " ORDER BY price ASC"; break;
    case 'price_high': $sql .= " ORDER BY price DESC"; break;
    case 'name': $sql .= " ORDER BY name ASC"; break;
    default: $sql .= " ORDER BY product_id DESC"; break;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) { $products = []; }

try {
    $cat_stmt = $pdo->query("SELECT DISTINCT category FROM Products WHERE is_active = TRUE ORDER BY category ASC");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $categories = []; }

$cart_count = 0;
if ($is_logged_in) {
    try {
        $cart_stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM Cart_Items WHERE user_id = ?");
        $cart_stmt->execute([$user_id]);
        $cart_count = (int)$cart_stmt->fetchColumn();
    } catch (PDOException $e) {}
}

// Quick add to cart AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_add') {
    header('Content-Type: application/json');
    if (!$is_logged_in) { echo json_encode(['ok'=>false,'error'=>'login_required']); exit; }
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    try {
        $pdo->prepare("INSERT INTO Cart_Items (user_id, product_id, quantity) VALUES (?, ?, ?)")->execute([$user_id, $pid, $qty]);
        echo json_encode(['ok'=>true,'cart_count'=>$cart_count+$qty]);
    } catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
    exit;
}

$cat_icons = ['Rackets'=>'sports_tennis','Shuttlecocks'=>'sports_baseball','Shoes'=>'footwear','Jersey'=>'checkroom','Accessories'=>'sports_martial_arts','Other'=>'inventory_2'];
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Shop | ShuttleSync</title>
    <?php include __DIR__ . '/includes/tailwind_config.php'; ?>
    <style>
        @keyframes fadeSlideUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
        .anim-in{animation:fadeSlideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
        .anim-d1{animation-delay:.05s;opacity:0;}
        .anim-d2{animation-delay:.1s;opacity:0;}
        .anim-d3{animation-delay:.15s;opacity:0;}
        .anim-d4{animation-delay:.2s;opacity:0;}
        .product-card{background:#fff;border-radius:20px;border:1px solid rgba(0,0,0,0.06);overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.04);transition:all .3s cubic-bezier(.22,1,.36,1);}
        .product-card:hover{box-shadow:0 12px 40px rgba(0,0,0,0.1);transform:translateY(-4px);}
        .product-card:hover .product-img img{transform:scale(1.08);}
        .product-card:hover .quick-actions{opacity:1;transform:translateY(0);}
        .product-img{overflow:hidden;background:#f8f9fc;position:relative;}
        .product-img img{transition:transform .5s cubic-bezier(.22,1,.36,1);}
        .quick-actions{position:absolute;bottom:12px;left:12px;right:12px;opacity:0;transform:translateY(8px);transition:all .25s ease;display:flex;gap:8px;}
        .cat-card{border-radius:16px;border:1px solid rgba(0,0,0,0.06);padding:20px;text-align:center;cursor:pointer;transition:all .25s;background:#fff;}
        .cat-card:hover{border-color:#3145e6;box-shadow:0 4px 20px rgba(49,69,230,0.12);transform:translateY(-2px);}
        .cat-card.active{border-color:#3145e6;background:rgba(49,69,230,0.04);}
        .hero-banner{background:linear-gradient(135deg,#3145e6 0%,#1a2bc4 50%,#ed4a30 100%);border-radius:24px;position:relative;overflow:hidden;}
        .hero-banner::before{content:'';position:absolute;top:-50%;right:-20%;width:500px;height:500px;background:radial-gradient(circle,rgba(255,255,255,0.1) 0%,transparent 70%);pointer-events:none;}
        .hero-banner::after{content:'';position:absolute;bottom:-30%;left:10%;width:300px;height:300px;background:radial-gradient(circle,rgba(237,74,48,0.15) 0%,transparent 70%);pointer-events:none;}
        .sort-btn{padding:8px 16px;border-radius:12px;font-size:12px;font-weight:700;border:1.5px solid #e8eaf0;cursor:pointer;transition:all .2s;background:#fff;}
        .sort-btn:hover{border-color:#3145e6;}
        .sort-btn.active{border-color:#3145e6;background:rgba(49,69,230,0.06);color:#3145e6;}
        .stock-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;}
        .stock-low{background:#fef3c7;color:#d97706;}
        .stock-ok{background:#d1fae5;color:#059669;}
        .stock-out{background:#fee2e2;color:#dc2626;}
        @keyframes cartBounce{0%,100%{transform:scale(1);}50%{transform:scale(1.3);}}
        .cart-bounce{animation:cartBounce .3s ease;}
    </style>
</head>
<body class="bg-surface min-h-screen antialiased">

<?php include __DIR__ . '/includes/nav_bar.php'; ?>

<main class="max-w-[1400px] mx-auto w-full px-4 md:px-8 pt-24 pb-20">

    <!-- Hero Banner -->
    <div class="hero-banner p-8 md:p-12 mb-10 anim-in relative z-10">
        <div class="relative z-10 flex flex-col md:flex-row items-center gap-8">
            <div class="flex-1">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-[10px] font-bold uppercase tracking-widest text-white mb-4">
                    <span class="material-symbols-outlined text-[14px]">bolt</span> ShuttleSync Pro Shop
                </span>
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-3 leading-tight">Gear Up for<br><span class="text-white/90">Your Best Game</span></h1>
                <p class="text-white/70 text-sm max-w-md leading-relaxed mb-6">Premium rackets, shoes, shuttlecocks and custom jerseys — everything you need to dominate the court.</p>
                <a href="#shop" class="inline-flex items-center gap-2 bg-white text-primary px-6 py-3 rounded-xl font-bold text-sm shadow-lg hover:shadow-xl transition-all active:scale-95">
                    <span class="material-symbols-outlined text-[18px]">shopping_bag</span> Browse Collection
                </a>
            </div>
            <div class="hidden md:flex items-center justify-center w-48 h-48">
                <span class="material-symbols-outlined text-white/20" style="font-size:160px;">sports_tennis</span>
            </div>
        </div>
    </div>

    <!-- Category Cards -->
    <div class="mb-10 anim-in anim-d1" id="shop">
        <div class="flex items-center justify-between mb-5">
            <div>
                <span class="text-accent text-[11px] font-bold tracking-[0.25em] uppercase mb-1 block">Browse by Category</span>
                <h2 class="text-xl font-bold">Shop Categories</h2>
            </div>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
            <a href="ecommerce" class="cat-card <?php echo empty($category_filter) ? 'active' : ''; ?>">
                <span class="material-symbols-outlined text-2xl mb-2 block <?php echo empty($category_filter) ? 'text-primary' : 'text-on-surface-variant'; ?>">apps</span>
                <span class="text-xs font-bold <?php echo empty($category_filter) ? 'text-primary' : 'text-on-surface'; ?>">All</span>
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="ecommerce?category=<?php echo urlencode($cat); ?>" class="cat-card <?php echo $category_filter === $cat ? 'active' : ''; ?>">
                <span class="material-symbols-outlined text-2xl mb-2 block <?php echo $category_filter === $cat ? 'text-primary' : 'text-on-surface-variant'; ?>"><?php echo $cat_icons[$cat] ?? 'inventory_2'; ?></span>
                <span class="text-xs font-bold <?php echo $category_filter === $cat ? 'text-primary' : 'text-on-surface'; ?>"><?php echo htmlspecialchars($cat); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Toolbar: Search + Sort + Count -->
    <div class="mb-6 anim-in anim-d2">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
            <!-- Search -->
            <form method="GET" action="ecommerce" class="relative w-full md:w-96">
                <?php if (!empty($category_filter)): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                <?php endif; ?>
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-lg">search</span>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search rackets, shoes, gear..." class="w-full pl-11 pr-10 py-3 bg-white border border-outline-variant/30 rounded-xl text-sm font-medium focus:border-primary focus:ring-2 focus:ring-primary/10 outline-none transition-all placeholder:text-on-surface-variant/40">
                <?php if (!empty($search_query) || !empty($category_filter)): ?>
                    <a href="ecommerce" class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 bg-surface flex items-center justify-center rounded-full text-on-surface-variant/60 hover:text-accent hover:bg-red-50 transition-all">
                        <span class="material-symbols-outlined text-[14px]">close</span>
                    </a>
                <?php endif; ?>
            </form>

            <div class="flex items-center gap-3 w-full md:w-auto">
                <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider hidden md:block">Sort:</span>
                <div class="flex gap-1.5 overflow-x-auto pb-1">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'newest'])); ?>" class="sort-btn <?php echo $sort==='newest'?'active':''; ?>">Newest</a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'price_low'])); ?>" class="sort-btn <?php echo $sort==='price_low'?'active':''; ?>">Price ↑</a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'price_high'])); ?>" class="sort-btn <?php echo $sort==='price_high'?'active':''; ?>">Price ↓</a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'name'])); ?>" class="sort-btn <?php echo $sort==='name'?'active':''; ?>">A–Z</a>
                </div>
                <span class="text-xs text-on-surface-variant/50 font-medium ml-2"><?php echo count($products); ?> items</span>
            </div>
        </div>
    </div>

    <!-- Product Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6 anim-in anim-d3">
        <?php if (count($products) > 0): ?>
            <?php foreach ($products as $product):
                $is_out = $product['stock_quantity'] <= 0;
                $is_low = $product['stock_quantity'] > 0 && $product['stock_quantity'] <= 5;
                $images = json_decode($product['image_url'], true);
                $img = (is_array($images) && count($images) > 0) ? $images[0] : ($product['image_url'] ?? '');
                if (!empty($img) && file_exists($img)) { $img_url = htmlspecialchars($img); }
                else { $img_url = "https://picsum.photos/seed/prod_".$product['product_id']."/400/400"; }
            ?>
            <div class="product-card flex flex-col h-full">
                <div class="product-img h-44 sm:h-52 md:h-56 relative flex items-center justify-center p-4">
                    <a href="products/product?id=<?php echo $product['product_id']; ?>" class="absolute inset-0 z-10">
                        <img loading="lazy" class="w-full h-full object-contain mix-blend-multiply" src="<?php echo $img_url; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </a>
                    <div class="absolute top-3 left-3 z-10">
                        <span class="px-2 py-0.5 bg-white/90 backdrop-blur-sm text-[10px] font-bold uppercase tracking-wider text-primary rounded-md shadow-sm"><?php echo htmlspecialchars($product['category']); ?></span>
                    </div>
                    <?php if ($is_out): ?>
                        <div class="absolute inset-0 bg-white/60 backdrop-blur-[1px] flex items-center justify-center z-10 rounded-t-[20px]">
                            <span class="bg-gray-800 text-white text-[10px] font-bold px-4 py-2 rounded-lg uppercase tracking-widest">Out of Stock</span>
                        </div>
                    <?php endif; ?>
                    <!-- Quick Actions (appear on hover) -->
                    <div class="quick-actions z-20">
                        <button onclick="event.preventDefault();quickAdd(<?php echo $product['product_id']; ?>,this)" <?php echo $is_out ? 'disabled' : ''; ?> class="flex-1 <?php echo $is_out ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-primary text-white hover:brightness-110'; ?> py-2.5 rounded-xl text-[11px] font-bold flex items-center justify-center gap-1 shadow-lg transition-all">
                            <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span> Add
                        </button>
                        <a href="products/product?id=<?php echo $product['product_id']; ?>" class="flex-1 bg-white/95 backdrop-blur-sm text-on-surface py-2.5 rounded-xl text-[11px] font-bold flex items-center justify-center gap-1 shadow-lg hover:bg-white transition-all">
                            <span class="material-symbols-outlined text-[16px]">visibility</span> View
                        </a>
                    </div>
                </div>

                <div class="p-4 flex flex-col flex-grow">
                    <a href="products/product?id=<?php echo $product['product_id']; ?>" class="block">
                        <h3 class="text-sm font-bold text-on-surface line-clamp-2 leading-snug mb-1 hover:text-primary transition-colors"><?php echo htmlspecialchars($product['name']); ?></h3>
                    </a>
                    <p class="text-[11px] text-on-surface-variant line-clamp-1 mb-3"><?php echo htmlspecialchars($product['description'] ?: 'Premium badminton gear'); ?></p>

                    <div class="mt-auto">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-lg font-bold text-primary">₱<?php echo number_format($product['price'], 2); ?></span>
                            <?php if ($is_out): ?>
                                <span class="stock-badge stock-out">Sold Out</span>
                            <?php elseif ($is_low): ?>
                                <span class="stock-badge stock-low">Only <?php echo $product['stock_quantity']; ?> left</span>
                            <?php else: ?>
                                <span class="stock-badge stock-ok">In Stock</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full py-20 text-center">
                <div class="w-20 h-20 bg-surface rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-on-surface-variant/30 text-4xl">search_off</span>
                </div>
                <h3 class="text-lg font-bold text-on-surface mb-2">No products found</h3>
                <p class="text-sm text-on-surface-variant/60 max-w-md mx-auto mb-6">
                    <?php if (!empty($search_query)): ?>
                        No results for "<span class="font-semibold text-on-surface"><?php echo htmlspecialchars($search_query); ?></span>". Try a different search.
                    <?php else: ?>
                        No products available in this category yet.
                    <?php endif; ?>
                </p>
                <a href="ecommerce" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:shadow-xl transition active:scale-95">
                    <span class="material-symbols-outlined text-[18px]">refresh</span> Browse All Products
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Trust Bar -->
    <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-4 anim-in anim-d4">
        <div class="bg-white rounded-2xl border border-outline-variant/10 p-5 text-center">
            <span class="material-symbols-outlined text-primary text-2xl mb-2 block">local_shipping</span>
            <p class="text-xs font-bold text-on-surface">Facility Pickup</p>
            <p class="text-[10px] text-on-surface-variant mt-1">On-site collection available</p>
        </div>
        <div class="bg-white rounded-2xl border border-outline-variant/10 p-5 text-center">
            <span class="material-symbols-outlined text-primary text-2xl mb-2 block">verified_user</span>
            <p class="text-xs font-bold text-on-surface">Secure Payment</p>
            <p class="text-[10px] text-on-surface-variant mt-1">GCash, PayPal, Card</p>
        </div>
        <div class="bg-white rounded-2xl border border-outline-variant/10 p-5 text-center">
            <span class="material-symbols-outlined text-primary text-2xl mb-2 block">sports_tennis</span>
            <p class="text-xs font-bold text-on-surface">Pro Quality</p>
            <p class="text-[10px] text-on-surface-variant mt-1">Tournament-grade gear</p>
        </div>
        <div class="bg-white rounded-2xl border border-outline-variant/10 p-5 text-center">
            <span class="material-symbols-outlined text-primary text-2xl mb-2 block">support_agent</span>
            <p class="text-xs font-bold text-on-surface">Support</p>
            <p class="text-[10px] text-on-surface-variant mt-1">Staff assistance on-site</p>
        </div>
    </div>
</main>

<!-- Mini Cart Toast -->
<div id="cartToast" class="fixed bottom-28 md:bottom-8 right-4 md:right-8 z-[100] bg-white rounded-2xl border border-outline-variant/20 shadow-2xl p-4 flex items-center gap-3 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none" style="pointer-events:auto;">
    <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-green-600 text-lg">check_circle</span>
    </div>
    <div>
        <p class="text-sm font-bold text-on-surface">Added to cart!</p>
        <a href="cart" class="text-[11px] text-primary font-bold hover:underline">View Cart →</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php include __DIR__ . '/includes/mobile_nav.php'; ?>

<script>
function quickAdd(productId, btn) {
    if (btn.disabled) return;
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">refresh</span>';
    btn.disabled = true;

    fetch('ecommerce', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=quick_add&product_id=' + productId + '&quantity=1'
    }).then(r => r.json()).then(d => {
        btn.innerHTML = origHTML;
        btn.disabled = false;
        if (d.ok) {
            // Show toast
            const toast = document.getElementById('cartToast');
            toast.classList.remove('translate-y-20', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
            setTimeout(() => { toast.classList.add('translate-y-20', 'opacity-0'); toast.classList.remove('translate-y-0', 'opacity-100'); }, 3000);
        } else if (d.error === 'login_required') {
            window.location.href = '/authentication/login';
        }
    }).catch(() => {
        btn.innerHTML = origHTML;
        btn.disabled = false;
    });
}
</script>

</body>
</html>
