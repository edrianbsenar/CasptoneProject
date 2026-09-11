<?php
session_start();
require_once __DIR__ . '/includes/csrf_helper.php';
require_once __DIR__ . '/includes/database_connect.php';

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$error_message = '';

const COURT_PRICE_PER_HOUR = 300.00;

$view_date = isset($_GET['date']) ? date('Y-m-d', strtotime($_GET['date'])) : date('Y-m-d');
$is_today = ($view_date === date('Y-m-d'));
$prev_date = date('Y-m-d', strtotime('-1 day', strtotime($view_date)));
$next_date = date('Y-m-d', strtotime('+1 day', strtotime($view_date)));

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'book_court') {
    if (!$is_logged_in) { header("Location: /authentication/login"); exit; }
    verify_csrf_token();
    try {
        $court_id = $_POST['court_id'];
        $start_time = $_POST['start_time'];
        $player_count = (int)$_POST['player_count'];
        $duration_hours = (int)$_POST['duration'];
        $end_time = date('H:i:s', strtotime("+{$duration_hours} hours", strtotime($start_time)));
        $total_price = COURT_PRICE_PER_HOUR * $duration_hours;

        $pdo->beginTransaction();
        $cs = $pdo->prepare("SELECT status FROM Courts WHERE court_id = ?");
        $cs->execute([$court_id]);
        if ($cs->fetchColumn() === 'Maintenance') throw new Exception("Court is under maintenance.");

        $chk = $pdo->prepare("SELECT booking_id FROM Bookings WHERE court_id = ? AND booking_date = ? AND status = 'Confirmed' AND start_time < ? AND end_time > ?");
        $chk->execute([$court_id, $view_date, $end_time, $start_time]);
        if ($chk->rowCount() > 0) throw new Exception("This time overlaps with an existing booking.");

        $ref = '#B-' . strtoupper(substr(uniqid(), -5));
        $has_created_col = (bool)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'Bookings'
               AND COLUMN_NAME  = 'created_at'"
        )->fetchColumn();
        if ($has_created_col) {
            $pdo->prepare("INSERT INTO Bookings (booking_reference, user_id, court_id, booking_date, start_time, end_time, player_count, total_price, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending Payment', NOW())")->execute([$ref, $user_id, $court_id, $view_date, $start_time, $end_time, $player_count, $total_price]);
        } else {
            $pdo->prepare("INSERT INTO Bookings (booking_reference, user_id, court_id, booking_date, start_time, end_time, player_count, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending Payment')")->execute([$ref, $user_id, $court_id, $view_date, $start_time, $end_time, $player_count, $total_price]);
        }
        $new_id = $pdo->lastInsertId();
        $pdo->commit();
        header("Location: book_and_pay.php?id=" . $new_id);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

$courts = $pdo->query("SELECT * FROM Courts ORDER BY court_id ASC")->fetchAll();
$existing_bookings_by_court = [];
try {
    $bs = $pdo->prepare("SELECT b.court_id, b.start_time, b.end_time, COALESCE(u.full_name, 'Guest') AS booker_name FROM Bookings b LEFT JOIN Users u ON b.user_id = u.user_id WHERE b.booking_date = ? AND b.status = 'Confirmed' ORDER BY b.start_time ASC");
    $bs->execute([$view_date]);
    foreach ($bs->fetchAll(PDO::FETCH_ASSOC) as $b) $existing_bookings_by_court[$b['court_id']][] = $b;
} catch (Exception$e) {}

$hours = range(9, 21);
$grid = [];
foreach ($courts as $court) {
    $cid = $court['court_id'];
    $grid[$cid] = ['id'=>$cid, 'name'=>$court['name'], 'status'=>$court['status']??'Available', 'open_count'=>0, 'total_count'=>count($hours), 'slots'=>[]];
    foreach ($hours as $h) {
        $ts = sprintf('%02d:00:00',$h);
        $grid[$cid]['slots'][$ts] = ['s'=>'open','md'=>22-$h];
    }
    if (isset($existing_bookings_by_court[$cid])) {
        foreach ($existing_bookings_by_court[$cid] as $b) {
            $sh=(int)date('H',strtotime($b['start_time'])); $eh=(int)date('H',strtotime($b['end_time']));
            $bn=trim($b['booker_name']);
            for($h=$sh;$h<$eh;$h++){ $ts=sprintf('%02d:00:00',$h); if(isset($grid[$cid]['slots'][$ts])) $grid[$cid]['slots'][$ts]=['s'=>'booked','md'=>0,'bn'=>$bn]; }
        }
    }
    foreach($hours as $h){
        $ts=sprintf('%02d:00:00',$h);
        if($grid[$cid]['slots'][$ts]['s']==='open'){
            $mx=0;
            for($nh=$h;$nh<22;$nh++){ $ns=sprintf('%02d:00:00',$nh); if($grid[$cid]['slots'][$ns]['s']==='booked')break;$mx++; }
            $grid[$cid]['slots'][$ts]['md']=$mx;
            $grid[$cid]['open_count']++;
        }
    }
}
$current_hour=(int)date('H'); $current_minute=(int)date('i');
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Book a Court | ShuttleSync</title>
    <?php include __DIR__ . '/includes/tailwind_config.php'; ?>
    <style>
        /* === DESKTOP TIMELINE === */
        .tl-container{position:relative;overflow:hidden;border-radius:24px;border:1px solid rgba(0,0,0,0.06);background:#fff;box-shadow:0 8px 40px rgba(0,0,0,0.06);}
        .tl-scroll{overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;scrollbar-width:thin;scrollbar-color:#3145e6 #f0f1f5;}
        .tl-scroll::-webkit-scrollbar{height:6px;}
        .tl-scroll::-webkit-scrollbar-track{background:#f0f1f5;border-radius:3px;}
        .tl-scroll::-webkit-scrollbar-thumb{background:#3145e6;border-radius:3px;}

        .tl-grid{display:inline-grid;grid-template-columns:160px repeat(<?php echo count($hours); ?>, minmax(56px, 1fr));min-width:100%;}

        .tl-corner{position:sticky;left:0;z-index:30;background:#fff;border-bottom:2px solid #e8eaf0;border-right:2px solid #e8eaf0;display:flex;align-items:flex-end;padding:12px 16px;}
        .tl-corner span{font-size:11px;font-weight:700;color:#727588;text-transform:uppercase;letter-spacing:0.1em;}

        .tl-hour-head{position:sticky;top:0;z-index:20;background:#fff;border-bottom:2px solid #e8eaf0;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:0.02em;padding:10px 0;}
        .tl-hour-head.is-now{color:#ed4a30;font-weight:800;}

        .tl-court-cell{position:sticky;left:0;z-index:10;background:#fff;border-right:2px solid #e8eaf0;border-bottom:1px solid #f0f1f5;display:flex;align-items:center;padding:0 16px;gap:12px;height:56px;}
        .tl-court-cell .court-icon{width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:800;color:#fff;}
        .tl-court-cell .court-name{font-size:13px;font-weight:700;color:#0f1118;line-height:1.2;}
        .tl-court-cell .court-meta{font-size:10px;color:#9ca3af;font-weight:500;margin-top:1px;}

        .tl-slot{height:56px;border-right:1px solid #f0f1f5;border-bottom:1px solid #f0f1f5;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s ease;position:relative;}
        .tl-slot.available{background:#f8fdf8;}
        .tl-slot.available:hover{background:#dcfce7;transform:scale(1.06);z-index:5;box-shadow:0 4px 16px rgba(34,197,94,0.15);border-radius:8px;}
        .tl-slot.booked{background:#fef2f2;cursor:not-allowed;flex-direction:column;gap:2px;}
        .tl-slot.booked::before{content:'';width:6px;height:6px;border-radius:50%;background:#fca5a5;flex-shrink:0;}
        .tl-booker{font-size:8px;font-weight:600;color:#b91c1c;line-height:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:52px;text-align:center;padding:0 2px;}
        .tl-slot.maint{background:#f5f5f5;cursor:not-allowed;opacity:0.4;}
        .tl-slot.maint::after{content:'';width:6px;height:6px;border-radius:50%;background:#d1d5db;}
        .tl-slot.chosen{background:linear-gradient(135deg,#3145e6,#1a2bc4)!important;border-radius:8px;transform:scale(1.08);z-index:10;box-shadow:0 6px 24px rgba(49,69,230,0.35);}
        .tl-slot.chosen::after{content:'';width:8px;height:8px;border-radius:50%;background:#fff;box-shadow:0 0 8px rgba(255,255,255,0.6);}

        /* Now line */
        .now-indicator{position:absolute;top:0;bottom:0;width:2px;background:#ed4a30;z-index:8;pointer-events:none;}
        .now-indicator::before{content:'NOW';position:absolute;top:-8px;left:50%;transform:translateX(-50%);font-size:8px;font-weight:800;color:#fff;background:#ed4a30;padding:2px 5px;border-radius:4px;white-space:nowrap;letter-spacing:0.05em;}

        /* === MOBILE CARDS === */
        .mobile-court-card{background:#fff;border-radius:20px;border:1px solid rgba(0,0,0,0.06);overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.04);}
        .mobile-court-header{padding:16px 20px;display:flex;align-items:center;gap:14px;border-bottom:1px solid #f0f1f5;}
        .mobile-court-header .mch-icon{width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:13px;}
        .mobile-court-header .mch-name{font-size:16px;font-weight:700;color:#0f1118;}
        .mobile-court-header .mch-meta{font-size:12px;color:#9ca3af;font-weight:500;}
        .mobile-slots{padding:16px 20px;display:flex;flex-wrap:wrap;gap:8px;}
        .mobile-slot{padding:8px 14px;border-radius:12px;font-size:12px;font-weight:700;border:1.5px solid transparent;transition:all .15s;cursor:pointer;}
        .mobile-slot.available{background:#f0fdf4;color:#16a34a;border-color:#bbf7d0;}
        .mobile-slot.available:active{transform:scale(0.95);background:#dcfce7;}
        .mobile-slot.booked{background:#fef2f2;color:#dc2626;border-color:#fecaca;cursor:not-allowed;text-decoration:line-through;opacity:0.6;display:inline-flex;flex-direction:column;align-items:flex-start;gap:2px;}
        .mobile-slot-booker{font-size:9px;font-weight:600;color:#b91c1c;text-decoration:none;opacity:0.8;line-height:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;}
        .mobile-slot.chosen{background:linear-gradient(135deg,#3145e6,#1a2bc4)!important;color:#fff!important;border-color:#3145e6!important;box-shadow:0 4px 12px rgba(49,69,230,0.3);}
        .mobile-slot-count{font-size:10px;color:#9ca3af;font-weight:500;margin-top:2px;}
        .mobile-no-slots{padding:20px;text-align:center;color:#9ca3af;font-size:13px;font-weight:500;}

        /* === BOOKING PANEL (Floating Card) === */
        .book-panel{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px) scale(0.95);z-index:90;background:#fff;border:1px solid rgba(0,0,0,0.06);box-shadow:0 12px 48px rgba(0,0,0,0.15);opacity:0;pointer-events:none;transition:all .3s cubic-bezier(.22,1,.36,1);border-radius:20px;width:360px;max-width:calc(100vw - 32px);}
        .book-panel.show{transform:translateX(-50%) translateY(0) scale(1);opacity:1;pointer-events:auto;}
        .book-panel-inner{padding:20px;}
        .bp-grid{display:flex;flex-direction:column;gap:14px;}

        /* === ANIMATIONS === */
        @keyframes fadeSlideUp{from{opacity:0;transform:translateY(30px);}to{opacity:1;transform:translateY(0);}}
        .anim-in{animation:fadeSlideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
        .anim-d1{animation-delay:.05s;opacity:0;}
        .anim-d2{animation-delay:.1s;opacity:0;}
        .anim-d3{animation-delay:.15s;opacity:0;}
    </style>
</head>
<body class="bg-surface min-h-screen antialiased">

<script>
const gridData=<?php echo json_encode($grid); ?>;
const hoursList=<?php echo json_encode($hours); ?>;
const isToday=<?php echo $is_today?'true':'false'; ?>;
const curH=<?php echo $current_hour; ?>;
const curM=<?php echo $current_minute; ?>;
const courtPricePerHour=<?php echo (int)COURT_PRICE_PER_HOUR; ?>;
</script>

<?php include __DIR__ . '/includes/nav_bar.php'; ?>

<main class="flex-1 max-w-[1400px] mx-auto w-full px-4 md:px-8 pt-24 pb-12">

    <?php if(!empty($error_message)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl flex items-center gap-3 text-red-600 anim-in">
            <span class="material-symbols-outlined text-[20px]">error</span>
            <p class="font-bold text-sm"><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4 anim-in anim-d1">
        <div>
            <span class="text-accent text-[11px] font-bold tracking-[0.25em] uppercase mb-2 block">Schedule</span>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight">Court Timeline</h1>
            <p class="text-on-surface-variant text-sm mt-1.5">Tap any available slot to book. <span class="font-semibold">₱<?php echo number_format(COURT_PRICE_PER_HOUR); ?></span>/hour.</p>
        </div>
        <div class="flex items-center gap-1.5 bg-white rounded-2xl border border-outline-variant/30 shadow-sm p-1">
            <a href="?date=<?php echo $prev_date; ?>" class="p-2.5 hover:bg-surface rounded-xl transition"><span class="material-symbols-outlined text-primary text-lg">chevron_left</span></a>
            <div class="flex flex-col items-center px-3 min-w-[140px]">
                <span class="text-[10px] text-accent font-bold uppercase tracking-widest"><?php echo date('M Y', strtotime($view_date)); ?></span>
                <span class="text-sm font-bold"><?php echo date('D, M j', strtotime($view_date)); ?></span>
            </div>
            <a href="?date=<?php echo $next_date; ?>" class="p-2.5 hover:bg-surface rounded-xl transition"><span class="material-symbols-outlined text-primary text-lg">chevron_right</span></a>
            <div class="border-l border-outline-variant/30 pl-2 ml-0.5">
                <input type="date" value="<?php echo $view_date; ?>" onchange="window.location.href='?date='+this.value" class="bg-transparent text-xs rounded-lg px-2 py-1.5 outline-none focus:ring-1 focus:ring-primary cursor-pointer">
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="flex items-center gap-5 mb-6 text-[11px] font-semibold anim-in anim-d2 flex-wrap">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-green-50 border border-green-200"></span>Available</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-red-50 border border-red-200"></span>Booked</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-gray-100 border border-gray-200"></span>Maintenance</span>
        <?php if($is_today): ?><span class="flex items-center gap-1.5"><span class="w-4 h-0.5 bg-accent rounded"></span>Now</span><?php endif; ?>
    </div>

    <!-- ================================ -->
    <!-- DESKTOP TIMELINE (hidden on mobile) -->
    <!-- ================================ -->
    <div class="tl-container anim-in anim-d3 hidden md:block">
        <div class="tl-scroll" id="tlScroll">
            <div class="tl-grid" id="tlGrid">
                <!-- Corner -->
                <div class="tl-corner"><span>Court</span></div>
                <!-- Hour headers -->
                <?php foreach($hours as $h):
                    $ampm=$h>=12?'PM':'AM'; $dh=$h%12?:12; $isNow=$is_today && $h===$current_hour;
                ?>
                    <div class="tl-hour-head <?php echo $isNow?'is-now':''; ?>"><?php echo "{$dh}{$ampm}"; ?></div>
                <?php endforeach; ?>

                <!-- Court rows -->
                <?php foreach($grid as $cid=>$c):
                    $colors=['#3145e6','#ed4a30','#1a2bc4','#c93520','#5b6ef0','#e07a5f'];
                    $bg=$colors[$cid%count($colors)];
                ?>
                    <div class="tl-court-cell">
                        <div class="court-icon" style="background:<?php echo $bg; ?>"><?php echo $cid; ?></div>
                        <div>
                            <div class="court-name"><?php echo htmlspecialchars($c['name']); ?></div>
                            <div class="court-meta"><?php echo $c['open_count']; ?>/<?php echo $c['total_count']; ?> open</div>
                        </div>
                    </div>
                    <?php if($c['status']==='Maintenance'): ?>
                        <div class="tl-slot maint" style="grid-column:span <?php echo count($hours); ?>;justify-content:center;gap:8px;opacity:1;background:repeating-linear-gradient(135deg,#f5f5f5,#f5f5f5 8px,#ebebeb 8px,#ebebeb 16px);">
                            <span class="material-symbols-outlined text-gray-400 text-[18px]">handyman</span>
                            <span style="font-size:11px;font-weight:800;color:#9ca3af;letter-spacing:0.1em;text-transform:uppercase;">MAINTENANCE</span>
                        </div>
                    <?php else: ?>
                    <?php foreach($hours as $h):
                        $ts=sprintf('%02d:00:00',$h);
                        $sl=$c['slots'][$ts]??['s'=>'booked','md'=>0,'bn'=>''];
                        $cls=$sl['s']==='booked'?'booked':'available';
                    ?>
                        <div class="tl-slot <?php echo $cls; ?>"
                             data-cid="<?php echo $cid; ?>" data-cname="<?php echo htmlspecialchars($c['name']); ?>"
                             data-ts="<?php echo $ts; ?>" data-h="<?php echo $h; ?>" data-md="<?php echo $sl['md']; ?>"
                             <?php if($sl['s']==='booked' && !empty($sl['bn'])): ?>title="<?php echo htmlspecialchars($sl['bn']); ?>"<?php endif; ?>
                             onclick="pickSlot(this,'desk')">
                            <?php if($sl['s']==='booked'): ?>
                                <?php if(!empty($sl['bn'])): ?>
                                    <span class="tl-booker"><?php echo htmlspecialchars($sl['bn']); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Now line -->
            <?php if($is_today): ?>
                <?php
                    $totalH=count($hours);
                    if($current_hour>=$hours[0] && $current_hour<=end($hours)){
                        $pct=(($current_hour-$hours[0])+$current_minute/60)/$totalH*100;
                        $leftPx=160+($pct/100)*(array_sum(array_map(function($h){return 56;},$hours)));
                    }
                ?>
                <?php if(isset($pct)): ?>
                    <div class="now-indicator" id="nowLine" style="left:<?php echo 160 + (($current_hour-$hours[0])+$current_minute/60)*(56); ?>px;top:40px;bottom:0;"></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================ -->
    <!-- MOBILE CARDS (shown on mobile only) -->
    <!-- ================================ -->
    <div class="md:hidden space-y-4 anim-in anim-d3">
        <?php foreach($grid as $cid=>$c):
            $colors=['#3145e6','#ed4a30','#1a2bc4','#c93520','#5b6ef0','#e07a5f'];
            $bg=$colors[$cid%count($colors)];
            $hasOpen=$c['open_count']>0 && $c['status']!=='Maintenance';
        ?>
        <div class="mobile-court-card">
            <div class="mobile-court-header">
                <div class="mch-icon" style="background:<?php echo $bg; ?>"><?php echo $cid; ?></div>
                <div class="flex-1">
                    <div class="mch-name"><?php echo htmlspecialchars($c['name']); ?></div>
                    <div class="mch-meta">₱<?php echo number_format(COURT_PRICE_PER_HOUR); ?>/hr • <?php echo $c['open_count']; ?> slots open</div>
                </div>
                <?php if($c['status']==='Maintenance'): ?>
                    <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-3 py-1 rounded-full">MAINTENANCE</span>
                <?php elseif(!$hasOpen): ?>
                    <span class="text-[10px] font-bold text-red-400 bg-red-50 px-3 py-1 rounded-full">FULL</span>
                <?php else: ?>
                    <span class="text-[10px] font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">OPEN</span>
                <?php endif; ?>
            </div>
            <?php if($hasOpen): ?>
                <div class="mobile-slots">
                    <?php foreach($hours as $h):
                        $ts=sprintf('%02d:00:00',$h);
                        $sl=$c['slots'][$ts]??['s'=>'booked','md'=>0,'bn'=>''];
                        $ampm=$h>=12?'PM':'AM'; $dh=$h%12?:12;
                        $mCls=$sl['s']==='booked'?'booked':'available';
                    ?>
                        <div class="mobile-slot <?php echo $mCls; ?>"
                             data-cid="<?php echo $cid; ?>" data-cname="<?php echo htmlspecialchars($c['name']); ?>"
                             data-ts="<?php echo $ts; ?>" data-h="<?php echo $h; ?>" data-md="<?php echo $sl['md']; ?>"
                             onclick="pickSlot(this,'mob')">
                            <div><?php echo "{$dh}:00 {$ampm}"; ?></div>
                            <?php if($sl['s']==='booked' && !empty($sl['bn'])): ?>
                                <div class="mobile-slot-booker"><?php echo htmlspecialchars($sl['bn']); ?></div>
                            <?php elseif($sl['s']==='open' && $sl['md']>1): ?>
                                <div class="mobile-slot-count"><?php echo $sl['md']; ?>h avail</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="mobile-no-slots"><?php echo $c['status']==='Maintenance'?'Under maintenance':'No slots available'; ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- ========================================== -->
<!-- BOOKING PANEL -->
<!-- ========================================== -->
<div class="book-panel" id="bookPanel">
    <div class="book-panel-inner">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-primary-dark flex items-center justify-center shrink-0 shadow-sm shadow-primary/20">
                    <span class="material-symbols-outlined text-white text-[18px]">stadium</span>
                </div>
                <div>
                    <div class="text-sm font-bold" id="bpCourt">Court</div>
                    <div class="text-[11px] text-on-surface-variant" id="bpTime">--</div>
                </div>
            </div>
            <button onclick="clearPick()" class="w-7 h-7 rounded-lg hover:bg-surface flex items-center justify-center transition">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">close</span>
            </button>
        </div>
        <div class="flex items-center gap-2 mb-3">
            <select id="bpDur" class="flex-1 bg-surface border border-outline-variant/50 rounded-lg px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer"></select>
            <input type="number" id="bpPlayers" min="1" max="20" value="2" class="w-16 bg-surface border border-outline-variant/50 rounded-lg px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-primary/20 text-center" placeholder="Pax">
        </div>
        <div class="flex items-center justify-between">
            <span class="text-lg font-bold text-primary" id="bpTotal">₱0</span>
            <?php if(!$is_logged_in): ?>
                <button onclick="window.location.href='/authentication/login'" class="bg-gradient-to-r from-accent to-accent-dark text-white px-5 py-2 rounded-xl font-bold text-xs shadow-md shadow-accent/20 transition flex items-center gap-1.5 active:scale-95">
                    <span class="material-symbols-outlined text-[16px]">login</span> Login to Book
                </button>
            <?php else: ?>
                <button onclick="doBook()" class="bg-gradient-to-r from-accent to-accent-dark text-white px-5 py-2 rounded-xl font-bold text-xs shadow-md shadow-accent/20 transition flex items-center gap-1.5 active:scale-95">
                    <span class="material-symbols-outlined text-[16px]">bolt</span> Book & Pay
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden form -->
<form id="bkForm" method="POST" action="?date=<?php echo $view_date; ?>" class="hidden">
    <?php echo get_csrf_input(); ?>
    <input type="hidden" name="action" value="book_court">
    <input type="hidden" id="fCid" name="court_id">
    <input type="hidden" id="fTime" name="start_time">
    <input type="hidden" id="fDur" name="duration">
    <input type="hidden" id="fPly" name="player_count">
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php include __DIR__ . '/includes/mobile_nav.php'; ?>

<script>
let chosen=null;
let chosenMode='desk';

function pickSlot(el,mode){
    if(el.classList.contains('maint'))return;
    const cls=mode==='desk'?'tl-slot':'mobile-slot';
    document.querySelectorAll('.'+cls+'.chosen').forEach(e=>e.classList.remove('chosen'));
    el.classList.add('chosen');
    chosen=el; chosenMode=mode;

    const h=parseInt(el.dataset.h);
    const ampm=h>=12?'PM':'AM';
    const dh=h%12||12;
    const eh=h+1; const eA=eh>=12?'PM':'AM'; const dEh=eh%12||12;
    document.getElementById('bpCourt').textContent=el.dataset.cname;
    document.getElementById('bpTime').textContent=`${dh}:00 ${ampm} – ${dEh}:00 ${eA} • <?php echo date('D, M j', strtotime($view_date)); ?>`;

    const md=parseInt(el.dataset.md);
    const sel=document.getElementById('bpDur');
    sel.innerHTML='';
    for(let i=1;i<=md;i++){
        const xh=h+i; const xA=xh>=12?'PM':'AM'; const xdh=xh%12||12;
        sel.innerHTML+=`<option value="${i}">${i} hr — until ${xdh}:00 ${xA}</option>`;
    }
    sel.onchange=updTotal; updTotal();

    document.getElementById('bookPanel').classList.add('show');
}

function updTotal(){
    const dur=parseInt(document.getElementById('bpDur').value)||1;
    document.getElementById('bpTotal').textContent='₱'+(dur*courtPricePerHour).toLocaleString();
}

function clearPick(){
    document.querySelectorAll('.chosen').forEach(e=>e.classList.remove('chosen'));
    chosen=null;
    document.getElementById('bookPanel').classList.remove('show');
}

function doBook(){
    if(!chosen)return;
    document.getElementById('fCid').value=chosen.dataset.cid;
    document.getElementById('fTime').value=chosen.dataset.ts;
    document.getElementById('fDur').value=document.getElementById('bpDur').value;
    document.getElementById('fPly').value=document.getElementById('bpPlayers').value;
    document.getElementById('bkForm').submit();
}

document.addEventListener('keydown',e=>{if(e.key==='Escape')clearPick();});

// Scroll to current time on desktop
document.addEventListener('DOMContentLoaded',()=>{
    <?php if($is_today && isset($pct)): ?>
        const sc=document.getElementById('tlScroll');
        if(sc){sc.scrollLeft=Math.max(0,<?php echo 160+(($current_hour-$hours[0])+$current_minute/60)*56; ?>-sc.clientWidth/3);}
    <?php endif; ?>
});
</script>
</body>
</html>