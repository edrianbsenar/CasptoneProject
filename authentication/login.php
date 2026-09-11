<?php
session_start();

require_once __DIR__ . '/../includes/database_connect.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

define('GOOGLE_CLIENT_ID',     '989508893804-3qq4ujq8952t4dh5me2hr70gpf80q29v.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI',  'https://hipowerbc.xyz/authentication/login');

function getGoogleAuthUrl() {
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
    ]);
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

function getGoogleAccessToken($code) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function getGoogleUserInfo($access_token) {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $access_token],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$google_login_url = getGoogleAuthUrl();

if (isset($_GET['code'])) {
    $token_data = getGoogleAccessToken($_GET['code']);
    if (isset($token_data['access_token'])) {
        $google_user = getGoogleUserInfo($token_data['access_token']);
        if (isset($google_user['email'])) {
            $email     = $google_user['email'];
            $full_name = $google_user['name'] ?? $email;
            $stmt = $pdo->prepare("SELECT user_id, full_name, email, role FROM Users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            if ($user) {
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['logged_in'] = true;
            } else {
                $role        = 'Player';
                $random_pass = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
                $insert_stmt = $pdo->prepare("INSERT INTO Users (full_name, email, password_hash, role) VALUES (:name, :email, :pass, :role)");
                $insert_stmt->execute(['name' => $full_name, 'email' => $email, 'pass' => $random_pass, 'role' => $role]);
                $_SESSION['user_id']   = $pdo->lastInsertId();
                $_SESSION['full_name'] = $full_name;
                $_SESSION['role']      = $role;
                $_SESSION['logged_in'] = true;
            }
            if ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Head Manager') {
                header("Location: /admin/dashboard");
            } else {
                header("Location: /playerdashboard");
            }
            exit;
        }
    }

    $error_message = "Google login failed. Please try again.";
}

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Head Manager') {
        header("Location: /admin/dashboard");
    } else {
        header("Location: /playerdashboard");
    }
    exit;
}
$error_message = $error_message ?? '';
$success_message = '';
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success_message = "Your password has been successfully reset. Please sign in.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_input    = trim($_POST['email']);
    $password_input = trim($_POST['password']);
    if (empty($login_input) || empty($password_input)) {
        $error_message = "Please enter both your email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT user_id, full_name, email, password_hash, role FROM Users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $login_input]);
        $user = $stmt->fetch();
        if ($user && password_verify($password_input, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['logged_in'] = true;
            if ($user['role'] === 'Admin' || $user['role'] === 'Head Manager') {
                header("Location: /admin/dashboard");
            } else {
                header("Location: /playerdashboard");
            }
            exit;
        } else {
            $error_message = "Invalid credentials. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Login | ShuttleSync</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { blue: '#3145e6', red: '#ed4a30', dark: '#0f172a' }
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }

        @keyframes pulse-ring { 0% { transform: scale(0.8); opacity: 1; } 100% { transform: scale(2.2); opacity: 0; } }
        @keyframes slide-up { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
        @keyframes gradient-shift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }

        .anim-slide-up { animation: slide-up 0.6s ease-out both; }
        .anim-slide-up-1 { animation-delay: 0.1s; }
        .anim-slide-up-2 { animation-delay: 0.2s; }
        .anim-slide-up-3 { animation-delay: 0.3s; }
        .anim-slide-up-4 { animation-delay: 0.4s; }
        .anim-gradient { background-size: 200% 200%; animation: gradient-shift 8s ease infinite; }

        .input-group input:focus ~ label,
        .input-group input:not(:placeholder-shown) ~ label {
            transform: translateY(-1.5rem) scale(0.85);
            color: #3145e6;
        }
        .input-group label { transition: all 0.2s ease; pointer-events: none; }

        .btn-primary { background: linear-gradient(135deg, #3145e6, #4f6eff); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 25px -5px rgba(49,69,230,0.4); }
        .btn-primary:active { transform: translateY(0); }

        .google-btn { transition: all 0.2s ease; border: 1.5px solid #e2e8f0; }
        .google-btn:hover { border-color: #cbd5e1; background: #f8fafc; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }

        .form-input { transition: all 0.2s ease; border: 1.5px solid #e2e8f0; }
        .form-input:focus { border-color: #3145e6; box-shadow: 0 0 0 3px rgba(49,69,230,0.1); outline: none; }

        .court-grid { background-image: linear-gradient(rgba(255,255,255,0.07) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.07) 1px, transparent 1px); background-size: 40px 40px; }

    </style>
</head>
<body class="bg-white min-h-screen">
<div class="flex min-h-screen">

    <!-- Left Branding Panel -->
    <div class="hidden lg:flex lg:w-[55%] relative overflow-hidden bg-gradient-to-br from-brand-dark via-[#1a2550] to-[#0d1b3e] anim-gradient" style="background-size: 200% 200%;">
        <div class="court-grid absolute inset-0"></div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col justify-center px-16 xl:px-20 max-w-2xl">
            <div class="anim-slide-up anim-slide-up-1">
                <div class="flex items-center gap-3 mb-8">
                    <img src="/img/logo.png" alt="Logo" class="w-12 h-12 rounded-xl shadow-lg shadow-black/20">
                    <div>
                        <span class="text-xl font-bold text-white tracking-tight">Shuttle</span><span class="text-xl font-bold text-brand-red tracking-tight">Sync</span>
                    </div>
                </div>
            </div>

            <h1 class="text-4xl xl:text-5xl font-extrabold text-white leading-tight mb-6 anim-slide-up anim-slide-up-2">
                Welcome back to<br>
                <span class="bg-gradient-to-r from-brand-blue to-blue-400 bg-clip-text text-transparent">your court.</span>
            </h1>

            <p class="text-lg text-blue-100/70 leading-relaxed mb-10 max-w-md anim-slide-up anim-slide-up-3">
                Sign in to manage your bookings, track your progress, and dominate the court with AI-powered insights.
            </p>

            <div class="flex gap-6 anim-slide-up anim-slide-up-4">
                <div class="flex items-center gap-2 text-blue-100/50 text-sm">
                    <span class="material-symbols-outlined text-brand-blue text-lg">check_circle</span>
                    Smart Booking
                </div>
                <div class="flex items-center gap-2 text-blue-100/50 text-sm">
                    <span class="material-symbols-outlined text-brand-blue text-lg">check_circle</span>
                    AI Analysis
                </div>
                <div class="flex items-center gap-2 text-blue-100/50 text-sm">
                    <span class="material-symbols-outlined text-brand-blue text-lg">check_circle</span>
                    Live Tracking
                </div>
            </div>
        </div>

        <!-- Bottom Gradient Fade -->
        <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-brand-dark/50 to-transparent"></div>
    </div>

    <!-- Right Form Panel -->
    <div class="w-full lg:w-[45%] flex items-center justify-center p-6 sm:p-8 lg:p-12 bg-white">
        <div class="w-full max-w-[420px]">

            <!-- Mobile Logo -->
            <div class="lg:hidden flex items-center gap-2 mb-8 anim-slide-up anim-slide-up-1">
                <img src="/img/logo.png" alt="Logo" class="w-10 h-10 rounded-xl">
                <span class="text-lg font-bold"><span class="text-brand-blue">Shuttle</span><span class="text-brand-red">Sync</span></span>
            </div>

            <!-- Back Link -->
            <a href="/" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-brand-blue transition-colors mb-6 group anim-slide-up anim-slide-up-1">
                <span class="material-symbols-outlined text-[18px] transition-transform group-hover:-translate-x-1">arrow_back</span>
                Home
            </a>

            <div class="anim-slide-up anim-slide-up-2">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Sign in</h2>
                <p class="text-gray-400 text-[15px] mb-8">Enter your credentials to access your account</p>
            </div>

            <?php if ($success_message): ?>
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3 anim-slide-up anim-slide-up-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-emerald-600 text-lg">check</span>
                    </div>
                    <p class="text-sm text-emerald-700 font-medium"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 anim-slide-up anim-slide-up-2">
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-red-600 text-lg">error</span>
                    </div>
                    <p class="text-sm text-red-600 font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <!-- Google Sign In -->
            <a href="<?php echo htmlspecialchars($google_login_url); ?>" class="google-btn w-full h-12 rounded-xl flex items-center justify-center gap-3 text-sm font-semibold text-gray-700 mb-6 anim-slide-up anim-slide-up-2">
                <svg class="w-5 h-5" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 12-4.53z" fill="#EA4335"/>
                </svg>
                Continue with Google
            </a>

            <div class="relative flex items-center py-2 mb-6 anim-slide-up anim-slide-up-3">
                <div class="flex-grow border-t border-gray-100"></div>
                <span class="flex-shrink mx-4 text-xs font-semibold text-gray-300 uppercase tracking-widest">or</span>
                <div class="flex-grow border-t border-gray-100"></div>
            </div>

            <!-- Login Form -->
            <form class="space-y-5" method="POST" action="login" id="loginForm">
                <div class="input-group relative anim-slide-up anim-slide-up-3">
                    <input class="form-input w-full h-12 px-4 rounded-xl bg-gray-50/80 text-sm font-medium text-gray-900" id="email" name="email" placeholder=" " required type="email"/>
                    <label class="absolute left-4 top-3 text-sm text-gray-400 font-medium" for="email">Email address</label>
                </div>

                <div class="input-group relative anim-slide-up anim-slide-up-3">
                    <input class="form-input w-full h-12 px-4 pr-11 rounded-xl bg-gray-50/80 text-sm font-medium text-gray-900" id="password" name="password" placeholder=" " required type="password"/>
                    <label class="absolute left-4 top-3 text-sm text-gray-400 font-medium" for="password">Password</label>
                    <button class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-600 transition-colors" onclick="togglePassword()" type="button">
                        <span class="material-symbols-outlined text-[20px]" id="pwIcon">visibility</span>
                    </button>
                </div>

                <div class="flex items-center justify-between anim-slide-up anim-slide-up-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input class="w-4 h-4 rounded border-gray-300 text-brand-blue focus:ring-brand-blue/20 cursor-pointer" id="remember" name="remember" type="checkbox"/>
                        <span class="text-sm text-gray-500 font-medium">Remember me</span>
                    </label>
                    <a href="/authentication/forgot_password" class="text-sm font-semibold text-brand-blue hover:text-blue-700 transition-colors">Forgot password?</a>
                </div>

                <button class="btn-primary w-full h-12 rounded-xl text-white text-sm font-bold tracking-wide flex items-center justify-center gap-2 anim-slide-up anim-slide-up-4" type="submit">
                    Sign In
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </form>

            <p class="text-center text-sm text-gray-400 mt-8 anim-slide-up anim-slide-up-4">
                Don't have an account? <a href="/authentication/register" class="font-bold text-brand-blue hover:text-blue-700 transition-colors">Create one free</a>
            </p>
        </div>
    </div>
</div>

<script>
    function togglePassword() {
        const pw = document.getElementById('password');
        const icon = document.getElementById('pwIcon');
        pw.type = pw.type === 'password' ? 'text' : 'password';
        icon.textContent = pw.type === 'password' ? 'visibility' : 'visibility_off';
    }
</script>
</body>
</html>
