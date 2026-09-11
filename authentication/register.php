<?php
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Head Manager') {
        header("Location: /admin/dashboard");
    } else {
        header("Location: /playerdashboard");
    }
    exit;
}

require_once __DIR__ . '/../includes/database_connect.php';

define('GOOGLE_CLIENT_ID',     '989508893804-3qq4ujq8952t4dh5me2hr70gpf80q29v.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI',  'https://hipowerbc.xyz/authentication/register');

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
            'code' => $code, 'client_id' => GOOGLE_CLIENT_ID, 'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => GOOGLE_REDIRECT_URI, 'grant_type' => 'authorization_code',
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

$google_register_url = getGoogleAuthUrl();
$error_message = '';

if (isset($_GET['code'])) {
    $token_data = getGoogleAccessToken($_GET['code']);
    if (isset($token_data['access_token'])) {
        $google_user = getGoogleUserInfo($token_data['access_token']);
        if (isset($google_user['email'])) {
            $email     = $google_user['email'];
            $full_name = $google_user['name'] ?? $email;
            $stmt = $pdo->prepare("SELECT user_id, full_name, role FROM Users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $existing_user = $stmt->fetch();
            if ($existing_user) {
                $_SESSION['user_id']   = $existing_user['user_id'];
                $_SESSION['full_name'] = $existing_user['full_name'];
                $_SESSION['role']      = $existing_user['role'];
                $_SESSION['logged_in'] = true;
            } else {
                $role = 'Player';
                $skill_level = 'Beginner';
                $random_pass = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
                try {
                    $insert_stmt = $pdo->prepare("INSERT INTO Users (full_name, email, password, role, skill_level) VALUES (:full_name, :email, :password, :role, :skill_level)");
                    $insert_stmt->execute([
                        'full_name' => $full_name, 'email' => $email, 'password' => $random_pass,
                        'role' => $role, 'skill_level' => $skill_level,
                    ]);
                    $_SESSION['user_id']   = $pdo->lastInsertId();
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['role']      = $role;
                    $_SESSION['logged_in'] = true;
                } catch (PDOException $e) {
                    $error_message = "Registration failed due to a system error. Please try again.";
                }
            }
            if (empty($error_message)) {
                header("Location: /playerdashboard");
                exit;
            }
        } else {
            $error_message = "Could not retrieve your Google account info. Please try again.";
        }
    } else {
        $error_message = "Google login failed. Please try again.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name   = trim($_POST['full_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    $skill_level = $_POST['skill'] ?? 'Beginner';

    if (empty($full_name) || empty($email) || empty($password)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error_message = "Passwords do not match.";
    } else {
        $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->rowCount() > 0) {
            $error_message = "An account with this email already exists.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Player';
            try {
                $insert_stmt = $pdo->prepare("INSERT INTO Users (full_name, email, password, role, skill_level) VALUES (:full_name, :email, :password, :role, :skill_level)");
                $insert_stmt->execute([
                    'full_name' => $full_name, 'email' => $email, 'password' => $password_hash,
                    'role' => $role, 'skill_level' => $skill_level,
                ]);
                $_SESSION['user_id']   = $pdo->lastInsertId();
                $_SESSION['full_name'] = $full_name;
                $_SESSION['role']      = $role;
                $_SESSION['logged_in'] = true;
                header("Location: /playerdashboard");
                exit;
            } catch (PDOException $e) {
                $error_message = "Registration failed due to a system error. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Register | ShuttleSync</title>
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
                    colors: { brand: { blue: '#3145e6', red: '#ed4a30', dark: '#0f172a' } }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }

        @keyframes slide-up { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes gradient-shift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .anim-slide-up { animation: slide-up 0.6s ease-out both; }
        .anim-slide-up-1 { animation-delay: 0.1s; }
        .anim-slide-up-2 { animation-delay: 0.2s; }
        .anim-slide-up-3 { animation-delay: 0.3s; }
        .anim-slide-up-4 { animation-delay: 0.4s; }
        .anim-slide-up-5 { animation-delay: 0.5s; }
        .anim-gradient { background-size: 200% 200%; animation: gradient-shift 8s ease infinite; }
        .shuttlecock { animation: shuttlecock 12s linear infinite; }

        .court-grid { background-image: linear-gradient(rgba(255,255,255,0.07) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.07) 1px, transparent 1px); background-size: 40px 40px; }

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

        .skill-card { transition: all 0.2s ease; border: 1.5px solid #e2e8f0; cursor: pointer; }
        .skill-card:hover { border-color: #cbd5e1; background: #f8fafc; }
        .skill-card.selected { border-color: #3145e6; background: rgba(49,69,230,0.04); }
        .skill-card.selected .skill-label { color: #3145e6; font-weight: 700; }
        .skill-card.selected .skill-icon { color: #3145e6; }

        .strength-bar { transition: width 0.4s ease, background-color 0.4s ease; }
    </style>
</head>
<body class="bg-white min-h-screen">
<div class="flex min-h-screen">

    <!-- Left Branding Panel -->
    <div class="hidden lg:flex lg:w-[55%] relative overflow-hidden bg-gradient-to-br from-brand-dark via-[#1a2550] to-[#0d1b3e] anim-gradient" style="background-size: 200% 200%;">
        <div class="court-grid absolute inset-0"></div>

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
                Join the<br>
                <span class="bg-gradient-to-r from-brand-red to-orange-400 bg-clip-text text-transparent">winning team.</span>
            </h1>

            <p class="text-lg text-blue-100/70 leading-relaxed mb-10 max-w-md anim-slide-up anim-slide-up-3">
                Create your free account and start booking courts, analyzing your game, and connecting with players.
            </p>

            <div class="flex gap-6 anim-slide-up anim-slide-up-4">
                <div class="flex items-center gap-2 text-blue-100/50 text-sm">
                    <span class="material-symbols-outlined text-brand-red text-lg">bolt</span>
                    Free to Start
                </div>
                <div class="flex items-center gap-2 text-blue-100/50 text-sm">
                    <span class="material-symbols-outlined text-brand-red text-lg">lock</span>
                    Secure Login
                </div>
                <div class="flex items-center gap-2 text-blue-100/50 text-sm">
                    <span class="material-symbols-outlined text-brand-red text-lg">speed</span>
                    Instant Access
                </div>
            </div>
        </div>

        <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-brand-dark/50 to-transparent"></div>
    </div>

    <!-- Right Form Panel -->
    <div class="w-full lg:w-[45%] flex items-center justify-center p-6 sm:p-8 lg:p-12 bg-white overflow-y-auto">
        <div class="w-full max-w-[420px] my-8">

            <!-- Mobile Logo -->
            <div class="lg:hidden flex items-center gap-2 mb-8 anim-slide-up anim-slide-up-1">
                <img src="/img/logo.png" alt="Logo" class="w-10 h-10 rounded-xl">
                <span class="text-lg font-bold"><span class="text-brand-blue">Shuttle</span><span class="text-brand-red">Sync</span></span>
            </div>

            <a href="/" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-brand-blue transition-colors mb-6 group anim-slide-up anim-slide-up-1">
                <span class="material-symbols-outlined text-[18px] transition-transform group-hover:-translate-x-1">arrow_back</span>
                Home
            </a>

            <div class="anim-slide-up anim-slide-up-2">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Create your account</h2>
                <p class="text-gray-400 text-[15px] mb-8">Start your badminton journey in seconds</p>
            </div>

            <?php if ($error_message): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 anim-slide-up anim-slide-up-2">
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-red-600 text-lg">error</span>
                    </div>
                    <p class="text-sm text-red-600 font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($google_register_url); ?>" class="google-btn w-full h-12 rounded-xl flex items-center justify-center gap-3 text-sm font-semibold text-gray-700 mb-6 anim-slide-up anim-slide-up-2">
                <svg class="w-5 h-5" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 12-4.53z" fill="#EA4335"/>
                </svg>
                Sign up with Google
            </a>

            <div class="relative flex items-center py-2 mb-6 anim-slide-up anim-slide-up-3">
                <div class="flex-grow border-t border-gray-100"></div>
                <span class="flex-shrink mx-4 text-xs font-semibold text-gray-300 uppercase tracking-widest">or</span>
                <div class="flex-grow border-t border-gray-100"></div>
            </div>

            <form class="space-y-4" method="POST" action="register" id="registerForm">
                <div class="input-group relative anim-slide-up anim-slide-up-3">
                    <input class="form-input w-full h-12 px-4 rounded-xl bg-gray-50/80 text-sm font-medium text-gray-900" id="full_name" name="full_name" placeholder=" " required type="text"/>
                    <label class="absolute left-4 top-3 text-sm text-gray-400 font-medium" for="full_name">Full name</label>
                </div>

                <div class="input-group relative anim-slide-up anim-slide-up-3">
                    <input class="form-input w-full h-12 px-4 rounded-xl bg-gray-50/80 text-sm font-medium text-gray-900" id="email" name="email" placeholder=" " required type="email"/>
                    <label class="absolute left-4 top-3 text-sm text-gray-400 font-medium" for="email">Email address</label>
                </div>

                <div class="input-group relative anim-slide-up anim-slide-up-3">
                    <input class="form-input w-full h-12 px-4 pr-11 rounded-xl bg-gray-50/80 text-sm font-medium text-gray-900" id="password" name="password" placeholder=" " required minlength="8" type="password" oninput="checkStrength()"/>
                    <label class="absolute left-4 top-3 text-sm text-gray-400 font-medium" for="password">Password</label>
                    <button class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-600 transition-colors" onclick="togglePw('password', this)" type="button">
                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                    </button>
                </div>

                <!-- Strength Bar -->
                <div class="-mt-2 flex items-center gap-2" id="strengthWrap" style="display:none;">
                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div id="strengthBar" class="strength-bar h-full rounded-full w-0"></div>
                    </div>
                    <span id="strengthLabel" class="text-[11px] font-bold"></span>
                </div>

                <div class="input-group relative anim-slide-up anim-slide-up-3">
                    <input class="form-input w-full h-12 px-4 pr-11 rounded-xl bg-gray-50/80 text-sm font-medium text-gray-900" id="confirm-password" name="confirm_password" placeholder=" " required type="password"/>
                    <label class="absolute left-4 top-3 text-sm text-gray-400 font-medium" for="confirm-password">Confirm password</label>
                    <button class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-600 transition-colors" onclick="togglePw('confirm-password', this)" type="button">
                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                    </button>
                </div>
                <p id="matchMsg" class="-mt-3 text-[11px] font-bold hidden"></p>

                <!-- Skill Level -->
                <div class="anim-slide-up anim-slide-up-4">
                    <label class="text-sm font-semibold text-gray-700 mb-3 block">Your skill level</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="skill-card selected rounded-xl p-3 flex items-center justify-between" onclick="selectSkill(this, 'Beginner')">
                            <span class="skill-label text-sm font-medium text-gray-700">Beginner</span>
                            <span class="material-symbols-outlined skill-icon text-brand-blue text-lg">school</span>
                        </div>
                        <div class="skill-card rounded-xl p-3 flex items-center justify-between" onclick="selectSkill(this, 'Intermediate')">
                            <span class="skill-label text-sm font-medium text-gray-700">Intermediate</span>
                            <span class="material-symbols-outlined skill-icon text-gray-300 text-lg">trending_up</span>
                        </div>
                        <div class="skill-card rounded-xl p-3 flex items-center justify-between" onclick="selectSkill(this, 'Advanced')">
                            <span class="skill-label text-sm font-medium text-gray-700">Advanced</span>
                            <span class="material-symbols-outlined skill-icon text-gray-300 text-lg">military_tech</span>
                        </div>
                        <div class="skill-card rounded-xl p-3 flex items-center justify-between" onclick="selectSkill(this, 'Pro')">
                            <span class="skill-label text-sm font-medium text-gray-700">Pro</span>
                            <span class="material-symbols-outlined skill-icon text-gray-300 text-lg" style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
                        </div>
                    </div>
                    <input type="hidden" name="skill" id="skillInput" value="Beginner">
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer anim-slide-up anim-slide-up-4">
                    <input class="mt-0.5 w-4 h-4 rounded border-gray-300 text-brand-blue focus:ring-brand-blue/20 cursor-pointer" id="terms" name="terms" type="checkbox" required/>
                    <span class="text-[13px] text-gray-400 leading-snug">I agree to the <a href="#" class="text-brand-blue hover:underline font-medium">Terms</a> and <a href="#" class="text-brand-blue hover:underline font-medium">Privacy Policy</a></span>
                </label>

                <button class="btn-primary w-full h-12 rounded-xl text-white text-sm font-bold tracking-wide flex items-center justify-center gap-2 anim-slide-up anim-slide-up-5" type="submit">
                    Create Account
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </form>

            <p class="text-center text-sm text-gray-400 mt-8 anim-slide-up anim-slide-up-5">
                Already have an account? <a href="/authentication/login" class="font-bold text-brand-blue hover:text-blue-700 transition-colors">Sign in</a>
            </p>
        </div>
    </div>
</div>

<script>
    function togglePw(id, btn) {
        const inp = document.getElementById(id);
        const icon = btn.querySelector('.material-symbols-outlined');
        inp.type = inp.type === 'password' ? 'text' : 'password';
        icon.textContent = inp.type === 'password' ? 'visibility' : 'visibility_off';
    }

    function selectSkill(el, level) {
        document.querySelectorAll('.skill-card').forEach(c => {
            c.classList.remove('selected');
            c.querySelector('.skill-icon').classList.add('text-gray-300');
            c.querySelector('.skill-icon').classList.remove('text-brand-blue');
        });
        el.classList.add('selected');
        el.querySelector('.skill-icon').classList.remove('text-gray-300');
        el.querySelector('.skill-icon').classList.add('text-brand-blue');
        document.getElementById('skillInput').value = level;
    }

    function checkStrength() {
        const pw = document.getElementById('password').value;
        const wrap = document.getElementById('strengthWrap');
        const bar = document.getElementById('strengthBar');
        const label = document.getElementById('strengthLabel');
        if (!pw) { wrap.style.display = 'none'; return; }
        wrap.style.display = 'flex';
        let s = 0;
        if (pw.length >= 6) s++;
        if (pw.length >= 8) s++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
        if (/[0-9]/.test(pw)) s++;
        if (/[^A-Za-z0-9]/.test(pw)) s++;
        if (s <= 2) { bar.style.width = '33%'; bar.className = 'strength-bar h-full rounded-full w-1/3 bg-red-400'; label.textContent = 'Weak'; label.className = 'text-[11px] font-bold text-red-500'; }
        else if (s <= 3) { bar.style.width = '66%'; bar.className = 'strength-bar h-full rounded-full w-2/3 bg-amber-400'; label.textContent = 'Fair'; label.className = 'text-[11px] font-bold text-amber-500'; }
        else { bar.style.width = '100%'; bar.className = 'strength-bar h-full rounded-full w-full bg-emerald-400'; label.textContent = 'Strong'; label.className = 'text-[11px] font-bold text-emerald-500'; }
    }

    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const pw = document.getElementById('password').value;
        const cpw = document.getElementById('confirm-password').value;
        if (pw !== cpw) { e.preventDefault(); alert('Passwords do not match.'); return; }
        if (!document.getElementById('terms').checked) { e.preventDefault(); alert('Please agree to the Terms and Conditions.'); }
    });
</script>
</body>
</html>
