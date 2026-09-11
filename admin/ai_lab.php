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

// ==========================================
// FETCH REAL METRICS FOR AI LAB
// ==========================================

// 1. Total distinct players who have used the AI tool
$ai_users_stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM AI_Analysis_Logs");
$active_ai_users = (int)$ai_users_stmt->fetchColumn();

// 2. Total registered players
$players_stmt = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'Player'");
$total_players = (int)$players_stmt->fetchColumn() ?: 1; // Fallback to 1 to prevent division by zero

// 3. Real Engagement Rate
$engagement_rate = round(($active_ai_users / $total_players) * 100, 1);

// 4. Fetch Top Performers (Highest Average Accuracy)
$leaderboard_stmt = $pdo->query("
    SELECT u.full_name, u.skill_level, ROUND(AVG(a.accuracy_score)) as avg_score, COUNT(a.log_id) as total_scans 
    FROM AI_Analysis_Logs a 
    JOIN Users u ON a.user_id = u.user_id 
    GROUP BY u.user_id 
    ORDER BY avg_score DESC 
    LIMIT 3
");
$top_performers = $leaderboard_stmt->fetchAll();

// 5. Fetch Heatmap Data (Most analyzed strokes)
$heatmap_stmt = $pdo->query("
    SELECT stroke_type, COUNT(*) as count 
    FROM AI_Analysis_Logs 
    GROUP BY stroke_type 
    ORDER BY count DESC
");
$stroke_stats_raw = $heatmap_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Calculate total scans to get percentages for the heatmap
$total_scans_stmt = $pdo->query("SELECT COUNT(*) FROM AI_Analysis_Logs");
$total_scans = (int)$total_scans_stmt->fetchColumn() ?: 1;

// Define default categories to ensure the UI boxes are always populated
$default_categories = ['Smash', 'Net Play', 'Cross Drop', 'Serve', 'Clear', 'Drive', 'Lift', 'Other'];
$heatmap_data = [];

foreach ($default_categories as $cat) {
    $count = $stroke_stats_raw[$cat] ?? 0;
    $pct = round(($count / $total_scans) * 100, 1);
    $heatmap_data[$cat] = $pct;
}

// Ensure any rogue "Other" categories get bundled into 'Other'
foreach ($stroke_stats_raw as $key => $count) {
    if (!in_array($key, $default_categories)) {
        $heatmap_data['Other'] += round(($count / $total_scans) * 100, 1);
    }
}

// Sort heatmap by percentage descending
arsort($heatmap_data);

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>AI Lab Engagement Insights | ShuttleSync</title>
    <?php include __DIR__ . '/../includes/tailwind_config.php'; ?>
</head>
<body class="bg-background text-on-surface min-h-screen">

<?php include __DIR__ . '/includes/admin_header.php'; ?>

    <main class="ml-0 md:ml-64 flex-grow p-gutter max-w-container-max mx-auto overflow-x-hidden">
        
        <header class="mb-margin-md pt-4 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
            <div>
                <h1 class="text-headline-xl font-headline-xl text-on-surface mb-2">AI Lab Insights</h1>
                <p class="text-body-lg font-body-lg text-on-surface-variant max-w-2xl">
                    Monitoring precision and performance analytics for the AI Coach ecosystem. 
                </p>
            </div>
            <div class="flex gap-4">
                <button class="bg-surface-container-lowest kinetic-border rounded-lg px-4 py-2 flex items-center gap-2 font-label-md text-label-md">
                    <span class="material-symbols-outlined text-primary">calendar_today</span> All Time
                </button>
                <button class="bg-primary text-on-primary rounded-lg px-6 py-2 flex items-center gap-2 font-label-md text-label-md hover:brightness-90 transition-all active:scale-95 shadow-lg shadow-primary/20">
                    <span class="material-symbols-outlined">download</span> Export Data
                </button>
            </div>
        </header>

        <section class="grid grid-cols-1 md:grid-cols-12 gap-gutter mb-gutter">
            
            <div class="md:col-span-4 bg-surface-container-lowest kinetic-border rounded-xl p-6 relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 transition-opacity group-hover:opacity-20 text-primary">
                    <span class="material-symbols-outlined !text-6xl">show_chart</span>
                </div>
                <p class="text-label-md font-label-md text-on-surface-variant uppercase tracking-wider mb-1">Coach Engagement Rate</p>
                <div class="flex items-baseline gap-2 mb-4">
                    <span class="text-headline-xl font-headline-xl text-primary"><?php echo $engagement_rate; ?>%</span>
                    <span class="text-label-sm font-label-sm text-green-600 flex items-center bg-green-50 px-2 py-0.5 rounded-full">
                        <span class="material-symbols-outlined text-[14px]">trending_up</span> Live
                    </span>
                </div>
                <div class="w-full bg-surface-container h-2 rounded-full mb-6 overflow-hidden">
                    <div class="bg-primary h-full rounded-full transition-all duration-1000" style="width: <?php echo $engagement_rate; ?>%; box-shadow: 0 0 12px rgba(0, 102, 138, 0.4);"></div>
                </div>
                <p class="text-label-sm font-label-sm text-outline">
                    <strong><?php echo $active_ai_users; ?></strong> out of <strong><?php echo $total_players; ?></strong> registered players are actively utilizing AI swing analysis.
                </p>
            </div>

            <div class="md:col-span-8 bg-surface-container-lowest kinetic-border rounded-xl p-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <p class="text-label-md font-label-md text-on-surface-variant uppercase tracking-wider">AI Coach Feedback Loops</p>
                        <h3 class="text-headline-md font-headline-md">Total Completed Analyses</h3>
                    </div>
                    <div class="flex gap-2">
                        <span class="flex items-center gap-1 text-label-sm font-label-sm text-primary font-bold">
                            <span class="w-3 h-3 rounded-full bg-primary"></span> Live Usage Trend
                        </span>
                    </div>
                </div>
                <div class="h-48 w-full relative">
                    <svg class="w-full h-full" preserveaspectratio="none" viewbox="0 0 1000 200">
                        <defs>
                            <lineargradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#00668a" stop-opacity="0.2"></stop>
                                <stop offset="100%" stop-color="#00668a" stop-opacity="0"></stop>
                            </lineargradient>
                        </defs>
                        <path d="M0,180 Q50,170 100,160 T200,140 T300,150 T400,110 T500,120 T600,80 T700,70 T800,40 T900,50 T1000,20 L1000,200 L0,200 Z" fill="url(#chartGradient)"></path>
                        <path d="M0,180 Q50,170 100,160 T200,140 T300,150 T400,110 T500,120 T600,80 T700,70 T800,40 T900,50 T1000,20" fill="none" stroke="#00668a" stroke-linecap="round" stroke-width="3"></path>
                    </svg>
                    <div class="absolute inset-0 flex justify-between items-end px-2 pointer-events-none opacity-20">
                        <div class="h-full w-px bg-outline-variant"></div>
                        <div class="h-full w-px bg-outline-variant"></div>
                        <div class="h-full w-px bg-outline-variant"></div>
                        <div class="h-full w-px bg-outline-variant"></div>
                        <div class="h-full w-px bg-outline-variant"></div>
                        <div class="h-full w-px bg-outline-variant"></div>
                        <div class="h-full w-px bg-outline-variant"></div>
                    </div>
                </div>
                <div class="flex justify-between mt-4 text-label-sm font-label-sm text-on-surface-variant uppercase font-bold tracking-widest">
                    <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-12 gap-gutter">
            
            <div class="md:col-span-7 bg-surface-container-lowest kinetic-border rounded-xl p-6">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h3 class="text-headline-md font-headline-md text-on-surface">Movement Analysis Heatmap</h3>
                        <p class="text-label-md font-label-md text-on-surface-variant">Popularity density of biomechanical movement tracking</p>
                    </div>
                    <div class="bg-primary-container/20 text-primary border border-primary/30 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest flex items-center gap-1">
                        <span class="w-2 h-2 bg-primary rounded-full animate-pulse"></span> DB Synced
                    </div>
                </div>
                
                <div class="grid grid-cols-4 gap-3 h-64">
                    <?php 
                    $colors = [
                        'bg-primary text-white shadow-lg',
                        'bg-primary/80 text-white',
                        'bg-primary/70 text-white',
                        'bg-primary/60 text-white',
                        'bg-surface-variant text-on-surface-variant',
                        'bg-surface-variant/70 text-on-surface-variant',
                        'bg-surface-container text-outline',
                        'bg-surface-container text-outline'
                    ];
                    $i = 0;
                    foreach ($heatmap_data as $category => $pct): 
                        $color_class = $colors[$i] ?? 'bg-surface-container text-outline';
                        $i++;
                    ?>
                    <div class="heatmap-cell <?php echo $color_class; ?> h-full rounded-lg p-4 flex flex-col justify-end cursor-default border border-outline-variant/30">
                        <span class="text-[10px] font-bold uppercase tracking-widest break-words"><?php echo htmlspecialchars($category); ?></span>
                        <span class="text-2xl font-bold mt-1"><?php echo $pct; ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="md:col-span-5 bg-surface-container-lowest kinetic-border rounded-xl p-6 overflow-hidden flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-headline-md font-headline-md text-on-surface">Top AI Lab Performers</h3>
                    <span class="material-symbols-outlined text-outline">emoji_events</span>
                </div>
                
                <div class="space-y-4 flex-grow">
                    <?php if(empty($top_performers)): ?>
                        <div class="text-center py-10 text-on-surface-variant italic border border-dashed border-outline-variant rounded-xl">
                            No AI Lab data yet. Test the system to generate leaders.
                        </div>
                    <?php else: ?>
                        <?php foreach($top_performers as $index => $player): 
                            $rank = $index + 1;
                            // Generate a consistent dummy avatar based on user ID or name
                            $seed = crc32($player['full_name']);
                            $avatar = "https://picsum.photos/seed/{$seed}/100/100";
                            
                            // Visuals for 1st, 2nd, 3rd
                            if($rank == 1) { $rank_bg = "bg-primary/10 text-primary"; $border = "border-2 border-primary"; }
                            elseif($rank == 2) { $rank_bg = "bg-outline-variant/20 text-outline"; $border = "border border-outline-variant"; }
                            else { $rank_bg = "bg-outline-variant/20 text-outline"; $border = "border border-outline-variant"; }
                        ?>
                        <div class="flex items-center justify-between p-3 rounded-lg hover:bg-surface-container-low transition-colors group border border-transparent hover:border-outline-variant/50">
                            <div class="flex items-center gap-4">
                                <div class="w-8 h-8 rounded-full <?php echo $rank_bg; ?> flex items-center justify-center font-bold text-sm">
                                    <?php echo $rank; ?>
                                </div>
                                <div class="w-10 h-10 rounded-full overflow-hidden <?php echo $border; ?>">
                                    <img alt="Athlete Avatar" class="w-full h-full object-cover" src="<?php echo $avatar; ?>"/>
                                </div>
                                <div>
                                    <p class="font-bold text-on-surface font-body-md truncate max-w-[150px]"><?php echo htmlspecialchars($player['full_name']); ?></p>
                                    <p class="text-[10px] text-primary uppercase font-bold tracking-widest"><?php echo htmlspecialchars($player['skill_level'] ?? 'Player'); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-headline-sm text-primary font-bold text-xl"><?php echo $player['avg_score']; ?> pts</p>
                                <p class="text-[10px] text-outline uppercase font-bold tracking-widest"><?php echo $player['total_scans']; ?> Scans</p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <button class="w-full mt-6 py-3 border border-outline-variant/50 rounded-lg text-label-md font-bold text-on-surface hover:bg-surface-bright transition-all active:scale-95 shadow-sm">View Full Leaderboard</button>
            </div>
        </section>

        <section class="mt-gutter bg-inverse-surface rounded-2xl p-margin-md text-on-primary-fixed-variant flex flex-col md:flex-row items-center gap-8 relative overflow-hidden shadow-xl">
            <div class="absolute inset-0 opacity-5 pointer-events-none">
                <svg height="100%" width="100%" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern height="40" id="grid" patternunits="userSpaceOnUse" width="40">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"></path>
                        </pattern>
                    </defs>
                    <rect fill="url(#grid)" height="100%" width="100%"></rect>
                </svg>
            </div>
            
            <div class="relative z-10 flex-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary/20 border border-primary/30 rounded-full text-primary-fixed-dim text-[10px] font-bold uppercase tracking-widest mb-4">
                    <span class="material-symbols-outlined text-[14px] animate-pulse-soft">auto_awesome</span> AI Recommended Action
                </div>
                <h2 class="text-headline-lg font-headline-lg text-white mb-4">Improve "Smash" Precision</h2>
                <p class="text-body-lg text-surface-variant max-w-xl mb-6">
                    System analytics indicate a 22% drop in smash accuracy for members during evening sessions. Consider deploying an automated "Power Hour" notification with swing correction tips for users booking 6 PM – 9 PM slots.
                </p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="announcements" class="bg-primary text-on-primary px-8 py-3 rounded-lg font-bold hover:brightness-110 active:scale-95 transition-all shadow-md text-center">Execute Campaign</a>
                    <button class="bg-transparent border border-surface-variant text-white px-8 py-3 rounded-lg hover:bg-white/10 transition-all font-bold">View Detail Analytics</button>
                </div>
            </div>
            
            <div class="relative z-10 w-full md:w-1/3 aspect-video bg-white/5 rounded-xl border border-white/10 p-6 backdrop-blur-md flex flex-col justify-center items-center text-center">
                <span class="material-symbols-outlined !text-6xl text-primary mb-4">batch_prediction</span>
                <h4 class="text-xl font-bold text-white mb-1">Projected Impact</h4>
                <p class="text-headline-xl font-headline-xl text-primary-fixed-dim font-black">+14.2%</p>
                <p class="text-xs text-surface-variant uppercase tracking-widest font-bold mt-2">Estimated user retention lift</p>
            </div>
        </section>
        
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
    document.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('mousedown', () => btn.classList.add('scale-95'));
        btn.addEventListener('mouseup', () => btn.classList.remove('scale-95'));
        btn.addEventListener('mouseleave', () => btn.classList.remove('scale-95'));
    });
</script>

</body>
</html>