<?php
session_start();
require_once __DIR__ . '/includes/database_connect.php';

// 1. Determine login state (Guests are allowed!)
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$full_name = $is_logged_in ? $_SESSION['full_name'] : 'Guest';

// Fetch cart count for the navigation badge
$cart_count = 0;
if ($is_logged_in) {
    try {
        $cart_stmt = $pdo->prepare("SELECT SUM(quantity) FROM Cart_Items WHERE user_id = ?");
        $cart_stmt->execute([$user_id]);
        $cart_count = $cart_stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        $cart_count = 0;
    }
}

// Fetch recent analyses for logged-in users
$recent_analyses = [];
if ($is_logged_in) {
    try {
        $log_stmt = $pdo->prepare("SELECT stroke_type, accuracy_score, feedback, analyzed_at FROM AI_Analysis_Logs WHERE user_id = ? ORDER BY analyzed_at DESC LIMIT 5");
        $log_stmt->execute([$user_id]);
        $recent_analyses = $log_stmt->fetchAll();
    } catch (PDOException $e) {
        $recent_analyses = [];
    }
}

// ==========================================
// HANDLE AJAX REQUEST: IMAGE ANALYSIS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'analyze_image') {
    
    header('Content-Type: application/json');

    try {
        if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please upload a valid image file.");
        }

        $file = $_FILES['image_file'];
        
        // STRICT BACKEND LIMIT: 10MB (10 * 1024 * 1024 bytes)
        if ($file['size'] > 10485760) {
            throw new Exception("File is too large! Maximum allowed size is 10MB.");
        }

        // CHECK EXTENSION FOR IMAGES ONLY
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            throw new Exception("Only JPG, PNG, or WEBP images are supported.");
        }

        $shot_type = $_POST['shot_type'] ?? 'General Play';
        
        // Save uploaded file to temp
        $tmp_dir = sys_get_temp_dir() . '/shuttlesync_ai';
        if (!is_dir($tmp_dir)) {
            mkdir($tmp_dir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safe_ext = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? $ext : 'jpg';
        $tmp_path = $tmp_dir . '/upload_' . bin2hex(random_bytes(8)) . '.' . $safe_ext;
        
        if (!move_uploaded_file($file['tmp_name'], $tmp_path)) {
            throw new Exception("Failed to save uploaded image.");
        }
        
        // Get AI config from .env
        require_once __DIR__ . '/includes/config.php';
        $ai_enabled = Config::get('AI_ENABLED', 'true');
        $python_path = Config::get('PYTHON_PATH', '/www/server/project/venv/bin/python');
        $script_path = __DIR__ . '/' . Config::get('AI_SCRIPT_PATH', 'ai/analysis_engine.py');
        
        if ($ai_enabled === 'true' && file_exists($script_path)) {
            // Call Python AI engine
            $cmd = escapeshellcmd($python_path);
            $args = escapeshellarg($script_path) . ' ' . escapeshellarg($tmp_path) . ' ' . escapeshellarg($shot_type);
            $full_cmd = "$cmd $args 2>/dev/null";
            
            $output = [];
            $return_code = 0;
            exec($full_cmd, $output, $return_code);
            
            @unlink($tmp_path); // Clean up temp file
            
            if ($return_code === 0 && !empty($output)) {
                $json_str = implode("\n", $output);
                $result = json_decode($json_str, true);
                
                if ($result && isset($result['success']) && $result['success']) {
                    // Log to AI_Analysis_Logs
                    if (session_status() === PHP_SESSION_NONE) session_start();
                    $log_uid = $_SESSION['user_id'] ?? null;
                    if ($log_uid) {
                        try {
                            require_once __DIR__ . '/includes/database_connect.php';
                            $acc = $result['data']['accuracy'] ?? 0;
                            $stroke = $result['data']['stroke_type'] ?? $shot_type;
                            $feedback = $result['data']['feedback'] ?? '';
                            $pdo->prepare("INSERT INTO AI_Analysis_Logs (user_id, stroke_type, accuracy_score, feedback, analyzed_at) VALUES (?, ?, ?, ?, NOW())")->execute([$log_uid, $stroke, $acc, $feedback]);
                        } catch (Exception $e) {}
                    }
                    echo json_encode($result);
                    exit;
                } else {
                    // AI returned error, fall through to fallback
                    $ai_error = $result['error'] ?? 'Unknown AI error';
                }
            } else {
                $ai_error = "Python script failed with code: $return_code";
            }
        } else {
            @unlink($tmp_path);
            $ai_error = "AI engine not available (AI_ENABLED=$ai_enabled)";
        }
        
        // Fallback: If AI engine fails, use basic image analysis
        $fallback_accuracy = 50;
        $fallback_feedback = "The AI analysis engine is currently unavailable. Please try again later or contact support.";
        
        echo json_encode([
            "success" => true,
            "data" => [
                "stroke_type" => $shot_type,
                "verdict" => "Needs Work",
                "accuracy" => $fallback_accuracy,
                "feedback" => $fallback_feedback,
                "similarity_score" => 0,
                "sub_scores" => ["footwork" => 0, "posture" => 0, "swing" => 0],
                "reference_skeleton" => null,
                "detected_skeleton" => null,
                "quality_score" => 0,
                "quality_warnings" => ["AI engine unavailable. Analysis could not be completed."],
                "detected_shot_type" => $shot_type,
                "detection_confidence" => 0,
                "shot_type_scores" => [],
                "all_similarities" => [],
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>AI Coach | ShuttleSync</title>
    <?php include __DIR__ . '/includes/tailwind_config.php'; ?>
    <style>
        .upload-zone { transition: all 0.3s ease; }
        .upload-zone.dragover { border-color: #3145e6; background: rgba(49,69,230,0.08); transform: scale(1.01); }
        .upload-zone.has-image { border-color: #3145e6; background: rgba(49,69,230,0.03); }
        .scan-line-anim { position: absolute; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, transparent, #3145e6, transparent); animation: scanMove 2s ease-in-out infinite; z-index: 20; }
        @keyframes scanMove { 0% { top: 0; } 50% { top: 100%; } 100% { top: 0; } }
        @keyframes pulse-ring { 0% { transform: scale(0.9); opacity: 1; } 100% { transform: scale(1.4); opacity: 0; } }
        .pulse-ring::after { content: ''; position: absolute; inset: -8px; border-radius: 50%; border: 3px solid currentColor; animation: pulse-ring 1.5s ease-out infinite; }
        .score-bar-fill { transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        .history-card { transition: all 0.2s ease; }
        .history-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    </style>
</head>
<body class="bg-background text-on-surface min-h-screen flex flex-col">

<?php include __DIR__ . '/includes/nav_bar.php'; ?>

<main class="flex-grow max-w-7xl mx-auto w-full px-4 md:px-8 py-8 pt-24">
    
    <!-- Hero Section -->
    <div class="mb-10">
        <div class="flex flex-col md:flex-row items-start md:items-center gap-4 mb-3">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary to-accent flex items-center justify-center shadow-lg">
                <span class="material-symbols-outlined text-white text-[28px]">psychology</span>
            </div>
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-on-surface tracking-tight">AI Biomechanical Coach</h1>
                <p class="text-on-surface-variant mt-1">Powered by MediaPipe Pose Detection &middot; Real-time skeletal analysis</p>
            </div>
        </div>
    </div>

    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
        
        <!-- Left: Upload Panel -->
        <div class="col-span-1 lg:col-span-5 flex flex-col gap-5">
            <!-- Shot Type Selector -->
            <div class="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/40 shadow-kinetic">
                <label class="text-xs font-bold text-outline uppercase tracking-widest mb-2 block">Shot Type</label>
                <div class="relative">
                    <select id="shotSelection" class="w-full bg-surface border border-outline-variant/50 rounded-xl px-4 py-3 text-sm text-on-surface font-semibold focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all appearance-none cursor-pointer">
                        <option value="Smash">Jump Smash</option>
                        <option value="Serve">Short / Flick Serve</option>
                        <option value="Cross Drop">Cross Court Drop</option>
                        <option value="Net Play">Net Kill / Spin</option>
                        <option value="General Play">General Footwork Base</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-outline pointer-events-none text-[20px]">expand_more</span>
                </div>
            </div>

            <!-- Upload Zone -->
            <div id="dropZone" class="upload-zone flex-grow border-2 border-dashed border-outline-variant/50 rounded-2xl p-6 text-center bg-surface-container-lowest hover:border-primary/40 transition-all cursor-pointer group relative flex flex-col items-center justify-center overflow-hidden min-h-[280px]">
                <div id="scanLine" class="scan-line-anim hidden"></div>
                <input type="file" id="imageInput" accept="image/png, image/jpeg, image/webp" class="hidden">
                
                <!-- Upload State -->
                <div id="uploadState" class="flex flex-col items-center py-4 w-full">
                    <div class="relative w-full flex flex-col items-center">
                        <canvas id="refSkeletonCanvas" width="240" height="280" class="mb-3 opacity-60"></canvas>
                        <p class="text-[10px] font-bold text-primary uppercase tracking-widest mb-1" id="refShotLabel">Ideal: Jump Smash</p>
                    </div>
                    <p class="text-base font-bold text-on-surface mb-1">Drop your action shot</p>
                    <p class="text-xs text-on-surface-variant mb-4">Match the reference pose above &middot; JPG, PNG, WEBP &middot; Max 10MB</p>
                    <div class="flex gap-2 justify-center">
                        <button type="button" id="browseBtn" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold shadow-md">
                            <span class="material-symbols-outlined text-[18px] mr-1 align-middle">folder_open</span> Browse
                        </button>
                        <button type="button" id="cameraBtn" class="bg-surface border border-outline-variant/50 px-5 py-2.5 rounded-xl text-sm font-bold shadow-md hover:border-primary/40 hover:bg-primary/5 transition-all">
                            <span class="material-symbols-outlined text-[18px] mr-1 align-middle">photo_camera</span> Camera
                        </button>
                    </div>
                </div>

                <!-- Preview State (hidden by default) -->
                <div id="previewState" class="hidden absolute inset-0 p-3">
                    <img id="previewImage" class="w-full h-full object-contain rounded-xl" alt="Preview">
                    <canvas id="detectedSkeletonCanvas" class="absolute inset-3 w-[calc(100%-24px)] h-[calc(100%-24px)] rounded-xl pointer-events-none"></canvas>
                    <button type="button" id="clearPreview" class="absolute top-4 right-4 w-8 h-8 bg-surface/80 backdrop-blur rounded-full flex items-center justify-center hover:bg-error hover:text-white transition-all border border-outline-variant/50 z-20">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                <!-- Processing State (hidden by default) -->
                <div id="processingState" class="hidden flex-col items-center justify-center z-10 w-full h-full bg-surface/95 absolute inset-0 rounded-2xl">
                    <div class="relative mb-4">
                        <div class="w-16 h-16 rounded-full border-4 border-primary/20 border-t-primary animate-spin"></div>
                        <span class="material-symbols-outlined text-primary text-2xl absolute inset-0 flex items-center justify-center">motion_sensor</span>
                    </div>
                    <h3 class="text-lg font-bold text-on-surface mb-1">Analyzing Posture...</h3>
                    <p class="text-xs text-on-surface-variant">Detecting keypoints &middot; Measuring angles</p>
                </div>
            </div>

            <!-- Quick Tips -->
            <div class="bg-primary/5 border border-primary/15 rounded-2xl p-4">
                <h4 class="text-xs font-bold text-primary uppercase tracking-widest mb-2 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">tips_and_updates</span> Tips for Best Results
                </h4>
                <ul class="space-y-1.5 text-xs text-on-surface-variant">
                    <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] text-primary mt-0.5">check_circle</span> Full body visible head-to-toe</li>
                    <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] text-primary mt-0.5">check_circle</span> Side or 45-degree angle works best</li>
                    <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] text-primary mt-0.5">check_circle</span> Good lighting, minimal background clutter</li>
                    <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] text-primary mt-0.5">check_circle</span> High resolution photo (1024x768+)</li>
                    <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] text-primary mt-0.5">check_circle</span> Match the reference pose shown above</li>
                </ul>
            </div>
        </div>

        <!-- Right: Results Panel -->
        <div class="col-span-1 lg:col-span-7 bg-surface-container-lowest rounded-2xl p-6 md:p-8 shadow-kinetic border border-outline-variant/40 flex flex-col">
            
            <!-- Score Header -->
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 border-b border-outline-variant/30 pb-6 mb-6">
                <div class="flex items-center gap-4">
                    <div class="radial-gauge flex items-center justify-center shrink-0 shadow-inner relative" id="mainGauge">
                        <div class="text-center relative z-10">
                            <span class="text-4xl font-bold text-primary block leading-none" id="overallScore">--</span>
                            <span class="text-[10px] text-on-surface-variant uppercase font-bold tracking-widest" id="overallVerdict">Pending</span>
                        </div>
                    </div>
                    <div class="hidden sm:block">
                        <p class="text-[10px] font-bold text-outline uppercase tracking-widest mb-1">Reference Match</p>
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-24 bg-surface-variant rounded-full overflow-hidden">
                                <div class="h-full bg-accent rounded-full score-bar-fill" id="similarityBar" style="width:0%"></div>
                            </div>
                            <span class="text-sm font-bold text-accent" id="similarityScore">--%</span>
                        </div>
                    </div>
                </div>
                <div class="text-center sm:text-left mt-1">
                    <h4 class="text-lg font-bold uppercase text-on-surface tracking-wide mb-1">Performance Score</h4>
                    <p class="text-sm text-on-surface-variant max-w-sm leading-relaxed">Your form is auto-detected and scored against the <span id="refShotName" class="font-semibold text-primary">matched reference pose</span>.</p>
                </div>
            </div>

            <!-- Results Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 flex-grow">
                
                <!-- Sub-scores -->
                <div class="space-y-5 flex flex-col justify-center">
                    <h5 class="text-sm font-bold text-on-surface flex items-center gap-2 uppercase tracking-wide">
                        <span class="material-symbols-outlined text-primary text-[20px]">analytics</span> Kinematic Metrics
                    </h5>
                    
                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-sm font-semibold text-on-surface">
                                <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px] text-outline">directions_run</span> Footwork Base</span>
                                <span class="text-primary" id="scoreFootwork">--%</span>
                            </div>
                            <div class="h-2 bg-surface-variant rounded-full overflow-hidden">
                                <div class="h-full bg-primary rounded-full score-bar-fill" id="barFootwork" style="width:0%"></div>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-sm font-semibold text-on-surface">
                                <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px] text-outline">accessibility_new</span> Body Posture</span>
                                <span class="text-primary" id="scorePosture">--%</span>
                            </div>
                            <div class="h-2 bg-surface-variant rounded-full overflow-hidden">
                                <div class="h-full bg-primary rounded-full score-bar-fill" id="barPosture" style="width:0%"></div>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-sm font-semibold text-on-surface">
                                <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px] text-outline">sports_martial_arts</span> Swing Angle</span>
                                <span class="text-primary" id="scoreSwing">--%</span>
                            </div>
                            <div class="h-2 bg-surface-variant rounded-full overflow-hidden">
                                <div class="h-full bg-primary rounded-full score-bar-fill" id="barSwing" style="width:0%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Quality Warnings -->
                    <div id="qualityWarnings" class="hidden mt-3 space-y-1.5"></div>
                </div>
                
                <!-- Coach Feedback -->
                <div class="rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/5 to-primary/10 p-5 flex flex-col justify-center">
                    <h5 class="text-xs font-bold text-primary uppercase tracking-widest flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-[18px]">model_training</span> Coach Feedback
                    </h5>
                    <div id="aiFeedbackText" class="text-sm text-on-surface-variant leading-relaxed space-y-2">
                        <p>Upload an image of your stroke. The AI will auto-detect your shot type and score it against professional form.</p>
                    </div>
                    <!-- Shot Type Comparison Chart -->
                    <div id="shotComparison" class="hidden mt-4 pt-4 border-t border-primary/15"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Analyses (logged-in users only) -->
    <?php if ($is_logged_in && !empty($recent_analyses)): ?>
    <section class="mt-10">
        <h2 class="text-xl font-bold text-on-surface mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">history</span> Recent Analyses
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
            <?php foreach ($recent_analyses as $analysis): 
                $score = $analysis['accuracy_score'];
                $scoreColor = $score > 75 ? 'text-green-600 bg-green-50 border-green-200' : ($score > 50 ? 'text-amber-600 bg-amber-50 border-amber-200' : 'text-red-600 bg-red-50 border-red-200');
                $barColor = $score > 75 ? 'bg-green-500' : ($score > 50 ? 'bg-amber-500' : 'bg-red-500');
            ?>
            <div class="history-card bg-surface-container-lowest rounded-xl p-4 border border-outline-variant/30">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-on-surface truncate"><?php echo htmlspecialchars($analysis['stroke_type']); ?></span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full border <?php echo $scoreColor; ?>"><?php echo $score; ?>%</span>
                </div>
                <div class="h-1.5 bg-surface-variant rounded-full overflow-hidden mb-2">
                    <div class="h-full <?php echo $barColor; ?> rounded-full" style="width:<?php echo $score; ?>%"></div>
                </div>
                <p class="text-[10px] text-outline"><?php echo date('M d, g:i A', strtotime($analysis['analyzed_at'])); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>

<!-- Camera Modal -->
<div id="cameraModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
    <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-outline-variant/30">
        <div class="flex items-center justify-between px-5 py-4 border-b border-outline-variant/30">
            <h3 class="text-base font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[20px]">photo_camera</span> Capture Pose
            </h3>
            <button id="closeCameraModal" class="w-8 h-8 rounded-full bg-surface-variant/50 flex items-center justify-center hover:bg-error hover:text-white transition-all">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="relative bg-black aspect-[4/3]">
            <video id="cameraFeed" autoplay playsinline class="w-full h-full object-contain"></video>
            <canvas id="cameraCanvas" class="hidden"></canvas>
            <!-- Capture flash -->
            <div id="captureFlash" class="absolute inset-0 bg-white opacity-0 pointer-events-none transition-opacity duration-150"></div>
            <!-- Pose guide overlay -->
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                <div class="border-2 border-dashed border-white/25 rounded-2xl w-[60%] h-[85%]"></div>
            </div>
        </div>
        <div class="flex items-center justify-center gap-4 px-5 py-4 bg-surface-container-lowest">
            <button id="captureBtn" class="w-14 h-14 rounded-full bg-accent hover:bg-accent-dark text-white flex items-center justify-center shadow-lg shadow-accent/30 transition-all hover:scale-105 active:scale-95">
                <span class="material-symbols-outlined text-[28px]">photo_camera</span>
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php include __DIR__ . '/includes/mobile_nav.php'; ?>

<script>
    const dropZone = document.getElementById('dropZone');
    const imageInput = document.getElementById('imageInput');
    const shotSelection = document.getElementById('shotSelection');
    
    const uploadState = document.getElementById('uploadState');
    const previewState = document.getElementById('previewState');
    const previewImage = document.getElementById('previewImage');
    const clearPreview = document.getElementById('clearPreview');
    const processingState = document.getElementById('processingState');
    const scanLine = document.getElementById('scanLine');
    
    const overallScore = document.getElementById('overallScore');
    const overallVerdict = document.getElementById('overallVerdict');
    const aiFeedbackText = document.getElementById('aiFeedbackText');
    const mainGauge = document.getElementById('mainGauge');
    const refShotLabel = document.getElementById('refShotLabel');
    const refShotName = document.getElementById('refShotName');

    // ==========================================
    // REFERENCE SKELETON DRAWING
    // ==========================================
    const REFERENCE_POSES = {
        "Smash": { landmarks: {0:[0.48,0.08],11:[0.38,0.22],12:[0.58,0.20],13:[0.30,0.18],14:[0.68,0.10],15:[0.25,0.25],16:[0.72,0.03],23:[0.42,0.45],24:[0.54,0.44],25:[0.38,0.65],26:[0.58,0.60],27:[0.35,0.85],28:[0.62,0.78]} },
        "Serve": { landmarks: {0:[0.48,0.15],11:[0.40,0.30],12:[0.56,0.30],13:[0.32,0.38],14:[0.62,0.40],15:[0.28,0.48],16:[0.55,0.52],23:[0.43,0.52],24:[0.53,0.52],25:[0.40,0.70],26:[0.55,0.68],27:[0.37,0.88],28:[0.58,0.87]} },
        "Cross Drop": { landmarks: {0:[0.48,0.10],11:[0.40,0.24],12:[0.56,0.24],13:[0.34,0.22],14:[0.64,0.20],15:[0.30,0.28],16:[0.70,0.15],23:[0.43,0.46],24:[0.53,0.46],25:[0.40,0.65],26:[0.56,0.63],27:[0.37,0.85],28:[0.59,0.83]} },
        "Net Play": { landmarks: {0:[0.45,0.12],11:[0.38,0.26],12:[0.54,0.26],13:[0.32,0.30],14:[0.65,0.22],15:[0.28,0.35],16:[0.78,0.18],23:[0.42,0.48],24:[0.52,0.48],25:[0.36,0.62],26:[0.60,0.58],27:[0.32,0.80],28:[0.65,0.72]} },
        "General Play": { landmarks: {0:[0.48,0.10],11:[0.40,0.24],12:[0.56,0.24],13:[0.34,0.32],14:[0.62,0.32],15:[0.30,0.38],16:[0.66,0.38],23:[0.43,0.46],24:[0.53,0.46],25:[0.40,0.64],26:[0.56,0.64],27:[0.37,0.84],28:[0.59,0.84]} },
    };
    const SKELETON_CONNECTIONS = [[11,12],[11,13],[13,15],[12,14],[14,16],[11,23],[12,24],[23,24],[23,25],[24,26],[25,27],[26,28],[0,11],[0,12]];

    function drawReferenceSkeleton(shotType) {
        const canvas = document.getElementById('refSkeletonCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const w = canvas.width, h = canvas.height;
        ctx.clearRect(0, 0, w, h);

        const ref = REFERENCE_POSES[shotType] || REFERENCE_POSES["General Play"];
        const pad = 20;

        // Draw connections
        ctx.strokeStyle = 'rgba(49,69,230,0.25)';
        ctx.lineWidth = 2;
        ctx.setLineDash([4, 4]);
        SKELETON_CONNECTIONS.forEach(([a, b]) => {
            const la = ref.landmarks[a], lb = ref.landmarks[b];
            if (la && lb) {
                ctx.beginPath();
                ctx.moveTo(pad + la[0] * (w - 2*pad), pad + la[1] * (h - 2*pad));
                ctx.lineTo(pad + lb[0] * (w - 2*pad), pad + lb[1] * (h - 2*pad));
                ctx.stroke();
            }
        });
        ctx.setLineDash([]);

        // Draw joints
        Object.entries(ref.landmarks).forEach(([idx, pos]) => {
            const x = pad + pos[0] * (w - 2*pad);
            const y = pad + pos[1] * (h - 2*pad);
            ctx.beginPath();
            ctx.arc(x, y, 4, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(49,69,230,0.35)';
            ctx.fill();
            ctx.strokeStyle = 'rgba(49,69,230,0.5)';
            ctx.lineWidth = 1.5;
            ctx.stroke();
        });

        // Label joints
        const labels = {0:'Head',11:'L',12:'R',15:'L',16:'R',27:'L',28:'R'};
        const labelNames = {0:'',11:'Shoulder',12:'Shoulder',15:'Wrist',16:'Wrist',27:'Ankle',28:'Ankle'};
        ctx.font = '9px sans-serif';
        ctx.fillStyle = 'rgba(49,69,230,0.5)';
        Object.entries(labels).forEach(([idx, side]) => {
            const pos = ref.landmarks[idx];
            if (pos) {
                const x = pad + pos[0] * (w - 2*pad);
                const y = pad + pos[1] * (h - 2*pad);
                ctx.fillText(side + ' ' + (labelNames[idx]||''), x + 7, y + 3);
            }
        });
    }

    function drawDetectedSkeleton(detected, container) {
        const canvas = document.getElementById('detectedSkeletonCanvas');
        if (!canvas || !detected) return;

        // Wait for image to load to get actual dimensions
        const img = document.getElementById('previewImage');
        const rect = container.getBoundingClientRect();
        canvas.width = rect.width - 24;
        canvas.height = rect.height - 24;

        const ctx = canvas.getContext('2d');
        const w = canvas.width, h = canvas.height;
        ctx.clearRect(0, 0, w, h);

        const lms = detected.landmarks;
        const conns = detected.connections;

        // Draw connections
        ctx.strokeStyle = 'rgba(22,163,74,0.7)';
        ctx.lineWidth = 2.5;
        conns.forEach(([a, b]) => {
            const la = lms[String(a)], lb = lms[String(b)];
            if (la && lb && la.visibility > 0.3 && lb.visibility > 0.3) {
                ctx.beginPath();
                ctx.moveTo(la.x * w, la.y * h);
                ctx.lineTo(lb.x * w, lb.y * h);
                ctx.stroke();
            }
        });

        // Draw joints
        Object.entries(lms).forEach(([idx, lm]) => {
            if (lm.visibility > 0.3) {
                const x = lm.x * w, y = lm.y * h;
                ctx.beginPath();
                ctx.arc(x, y, 4, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(22,163,74,0.8)';
                ctx.fill();
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 1.5;
                ctx.stroke();
            }
        });
    }

    // Draw reference on load and shot change
    function updateRefSkeleton() {
        const shot = shotSelection.value;
        drawReferenceSkeleton(shot);
        const labels = {"Smash":"Jump Smash","Serve":"Short / Flick Serve","Cross Drop":"Cross Court Drop","Net Play":"Net Kill / Spin","General Play":"General Footwork Base"};
        if (refShotLabel) refShotLabel.textContent = 'Ideal: ' + (labels[shot] || shot);
        if (refShotName) refShotName.textContent = labels[shot] || shot;
    }
    shotSelection.addEventListener('change', updateRefSkeleton);
    updateRefSkeleton();

    // Drag & Drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => dropZone.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); }));
    dropZone.addEventListener('dragover', () => dropZone.classList.add('dragover'));
    ['dragleave', 'drop'].forEach(evt => dropZone.addEventListener(evt, () => dropZone.classList.remove('dragover')));
    
    dropZone.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files));
    dropZone.addEventListener('click', (e) => { if (!e.target.closest('#clearPreview')) imageInput.click(); });
    imageInput.addEventListener('change', function() { handleFiles(this.files); });

    clearPreview.addEventListener('click', (e) => {
        e.stopPropagation();
        imageInput.value = '';
        previewState.classList.add('hidden');
        uploadState.classList.remove('hidden');
        dropZone.classList.remove('has-image');
        // Clear detected skeleton
        const dc = document.getElementById('detectedSkeletonCanvas');
        if (dc) { dc.getContext('2d').clearRect(0, 0, dc.width, dc.height); }
    });

    // ==========================================
    // CAMERA CAPTURE
    // ==========================================
    const cameraModal = document.getElementById('cameraModal');
    const cameraFeed = document.getElementById('cameraFeed');
    const cameraCanvas = document.getElementById('cameraCanvas');
    const captureBtn = document.getElementById('captureBtn');
    const closeCameraModal = document.getElementById('closeCameraModal');
    const captureFlash = document.getElementById('captureFlash');
    const cameraBtn = document.getElementById('cameraBtn');
    const browseBtn = document.getElementById('browseBtn');
    let cameraStream = null;

    browseBtn.addEventListener('click', (e) => { e.stopPropagation(); imageInput.click(); });

    cameraBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        openCamera();
    });

    async function openCamera() {
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 960 } }
            });
            cameraFeed.srcObject = cameraStream;
            cameraModal.classList.remove('hidden');
            cameraModal.classList.add('flex');
        } catch (err) {
            alert("Camera access denied. Please allow camera permissions and try again.");
        }
    }

    function closeCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(t => t.stop());
            cameraStream = null;
        }
        cameraFeed.srcObject = null;
        cameraModal.classList.add('hidden');
        cameraModal.classList.remove('flex');
    }

    closeCameraModal.addEventListener('click', closeCamera);
    cameraModal.addEventListener('click', (e) => { if (e.target === cameraModal) closeCamera(); });

    captureBtn.addEventListener('click', () => {
        const ctx = cameraCanvas.getContext('2d');
        cameraCanvas.width = cameraFeed.videoWidth;
        cameraCanvas.height = cameraFeed.videoHeight;
        ctx.drawImage(cameraFeed, 0, 0);

        // Flash effect
        captureFlash.style.opacity = '0.8';
        setTimeout(() => { captureFlash.style.opacity = '0'; }, 150);

        cameraCanvas.toBlob((blob) => {
            if (!blob) return;
            const file = new File([blob], 'capture_' + Date.now() + '.jpg', { type: 'image/jpeg' });
            closeCamera();

            // Show preview
            const reader = new FileReader();
            reader.onload = (ev) => {
                previewImage.src = ev.target.result;
                uploadState.classList.add('hidden');
                previewState.classList.remove('hidden');
                dropZone.classList.add('has-image');
            };
            reader.readAsDataURL(file);
            uploadImage(file);
        }, 'image/jpeg', 0.92);
    });

    function handleFiles(files) { 
        if (files.length > 0) {
            const file = files[0];
            if (file.size > 10485760) {
                alert("File is too large! Maximum allowed size is 10MB.");
                imageInput.value = ""; 
                return;
            }
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImage.src = e.target.result;
                uploadState.classList.add('hidden');
                previewState.classList.remove('hidden');
                dropZone.classList.add('has-image');
            };
            reader.readAsDataURL(file);
            uploadImage(file);
        }
    }

    function uploadImage(file) {
        processingState.classList.remove('hidden');
        processingState.classList.add('flex');
        scanLine.classList.remove('hidden');

        let formData = new FormData();
        formData.append('action', 'analyze_image');
        formData.append('image_file', file);
        formData.append('shot_type', shotSelection.value);

        fetch('/aimovementanalysis', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            processingState.classList.remove('flex');
            processingState.classList.add('hidden');
            scanLine.classList.add('hidden');

            if (data.success) {
                const acc = data.data.accuracy;
                overallScore.textContent = acc;
                overallVerdict.textContent = data.data.verdict;
                
                const color = acc > 75 ? '#16a34a' : (acc > 50 ? '#d97706' : '#dc2626');
                mainGauge.style.background = `conic-gradient(${color} 0% ${acc}%, #e5eeff ${acc}% 100%)`;

                // Show detected shot type
                const detectedType = data.data.detected_shot_type || data.data.stroke_type;
                const detConf = data.data.detection_confidence || 0;
                const shotLabels = {"Smash":"Jump Smash","Serve":"Short / Flick Serve","Cross Drop":"Cross Court Drop","Net Play":"Net Kill / Spin","General Play":"General Footwork Base"};
                
                // Auto-switch dropdown to detected type
                const shotSelect = document.getElementById('shotSelection');
                if (shotSelect) {
                    for (let opt of shotSelect.options) {
                        if (opt.value === detectedType) { opt.selected = true; break; }
                    }
                    updateRefSkeleton();
                }

                // Similarity score
                const sim = data.data.similarity_score || 0;
                const simBar = document.getElementById('similarityBar');
                const simScore = document.getElementById('similarityScore');
                if (simBar) simBar.style.width = sim + '%';
                if (simScore) simScore.textContent = sim + '%';

                // Real sub-scores from AI engine
                const subs = data.data.sub_scores || {};
                const fW = subs.footwork || 0;
                const pW = subs.posture || 0;
                const sW = subs.swing || 0;

                document.getElementById('scoreFootwork').textContent = fW + '%';
                document.getElementById('barFootwork').style.width = fW + '%';
                
                document.getElementById('scorePosture').textContent = pW + '%';
                document.getElementById('barPosture').style.width = pW + '%';
                
                document.getElementById('scoreSwing').textContent = sW + '%';
                document.getElementById('barSwing').style.width = sW + '%';

                // Quality warnings
                const warnings = data.data.quality_warnings || [];
                const warnContainer = document.getElementById('qualityWarnings');
                if (warnings.length > 0 && warnContainer) {
                    warnContainer.classList.remove('hidden');
                    warnContainer.innerHTML = warnings.map(w =>
                        `<div class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                            <span class="material-symbols-outlined text-amber-600 text-[14px] mt-0.5 shrink-0">warning</span>
                            <span class="text-[11px] text-amber-800 leading-snug">${w}</span>
                        </div>`
                    ).join('');
                } else if (warnContainer) {
                    warnContainer.classList.add('hidden');
                }

                // Shot type comparison chart
                const allSims = data.data.all_similarities || {};
                const allScores = data.data.shot_type_scores || {};
                const compContainer = document.getElementById('shotComparison');
                if (compContainer && Object.keys(allSims).length > 0) {
                    const sorted = Object.entries(allSims).sort((a,b) => b[1] - a[1]);
                    compContainer.classList.remove('hidden');
                    compContainer.innerHTML = `
                        <p class="text-[10px] font-bold text-outline uppercase tracking-widest mb-2">Pose Match vs All Shots</p>
                        <div class="space-y-1.5">
                            ${sorted.map(([type, sim]) => {
                                const isDetected = type === detectedType;
                                const barColor = isDetected ? 'bg-primary' : 'bg-outline/30';
                                const textColor = isDetected ? 'text-primary font-bold' : 'text-on-surface-variant';
                                return `
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] ${textColor} w-20 shrink-0 truncate">${shotLabels[type] || type}</span>
                                    <div class="flex-1 h-1.5 bg-surface-variant rounded-full overflow-hidden">
                                        <div class="h-full ${barColor} rounded-full" style="width:${sim}%"></div>
                                    </div>
                                    <span class="text-[10px] ${textColor} w-8 text-right">${sim}%</span>
                                </div>`;
                            }).join('')}
                        </div>
                    `;
                }

                // Draw detected skeleton overlay
                if (data.data.detected_skeleton) {
                    drawDetectedSkeleton(data.data.detected_skeleton, previewState);
                }

                aiFeedbackText.innerHTML = `
                    <div class="bg-surface-container-lowest rounded-xl p-3 border border-outline-variant/30 mb-2">
                        <span class="text-[10px] font-bold text-outline uppercase tracking-widest">Detected Stroke</span>
                        <p class="text-sm font-bold text-on-surface">${shotLabels[detectedType] || detectedType} <span class="text-[10px] font-normal text-on-surface-variant">(${detConf}% confidence)</span></p>
                    </div>
                    <p class="text-sm text-on-surface-variant leading-relaxed">${data.data.feedback}</p>
                `;
            } else {
                alert(data.error);
            }
        }).catch(error => {
            processingState.classList.remove('flex');
            processingState.classList.add('hidden');
            scanLine.classList.add('hidden');
            alert("Network error. Could not connect to AI engine.");
        });
    }
</script>

</body>
</html>