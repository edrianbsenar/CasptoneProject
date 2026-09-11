<?php
session_start();
require_once __DIR__ . '/../includes/database_connect.php';

if (!isset($_SESSION['code_verified']) || $_SESSION['code_verified'] !== true) {
    header("Location: /authentication/forgot_password");
    exit;
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_SESSION['reset_email'];

    if (strlen($new_password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE Users SET password_hash = :hash, reset_code = NULL, code_expires = NULL WHERE email = :email");
        $stmt->execute([
            'hash' => $hashed_password,
            'email' => $email
        ]);

        session_unset();
        session_destroy();

        header("Location: /authentication/login?reset=success");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Create New Password | ShuttleSync</title>
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
        .court-grid { background-image: linear-gradient(rgba(255,255,255,0.07) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.07) 1px, transparent 1px); background-size: 40px 40px; }

        .input-group input:focus ~ label,
        .input-group input:not(:placeholder-shown) ~ label { transform: translateY(-1.5rem) scale(0.85); color: #3145e6; }
        .input-group label { transition: all 0.2s ease; pointer-events: none; }

        .btn-primary { background: linear-gradient(135deg, #3145e6, #4f6eff); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 25px -5px rgba(49,69,230,0.4); }
        .btn-primary:active { transform: translateY(0); }

        .form-input { transition: all 0.2s ease; border: 1.5px solid #e2e8f0; }
        .form-input:focus { border-color: #3145e6; box-shadow: 0 0 0 3px rgba(49,69,230,0.1); outline: none; }

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
                Almost<br>
                <span class="bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">there.</span>
            </h1>

            <p class="text-lg text-blue-100/70 leading-relaxed mb-10 max-w-md anim-slide-up anim-slide-up-3">
                Choose a strong password to secure your account. Make it something memorable but hard to guess.
            </p>

            <div class="flex gap-6 anim-slide-up anim-slide-up-4">
                <div class="flex items-center gap-2 text-blue-100/50 text-sm">
                    <span class="material-symbols-outlined text-emerald-400 text-lg">security</span>
                    Encrypted
                </div>
                <div class="flex items-center gap-2 text-blue-100/50 text-sm">
                    <span class="material-symbols-outlined text-emerald-400 text-lg">speed</span>
                    Instant update
                </div>
            </div>
        </div>

        <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-brand-dark/50 to-transparent"></div>
    </div>

    <!-- Right Form Panel -->
    <div class="w-full lg:w-[45%] flex items-center justify-center p-6 sm:p-8 lg:p-12 bg-white">
        <div class="w-full max-w-[420px]">

            <div class="lg:hidden flex items-center gap-2 mb-8 anim-slide-up anim-slide-up-1">
                <img src="/img/logo.png" alt="Logo" class="w-10 h-10 rounded-xl">
                <span class="text-lg font-bold"><span class="text-brand-blue">Shuttle</span><span class="text-brand-red">Sync</span></span>
            </div>

            <a href="/authentication/login" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-brand-blue transition-colors mb-6 group anim-slide-up anim-slide-up-1">
                <span class="material-symbols-outlined text-[18px] transition-transform group-hover:-translate-x-1">arrow_back</span>
                Back to login
            </a>

            <div class="anim-slide-up anim-slide-up-2">
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-emerald-600 text-[28px]">lock_reset</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Create new password</h2>
                <p class="text-gray-400 text-[15px] mb-8">Your identity is verified. Set a new password for your account.</p>
            </div>

            <?php if ($error_message): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 anim-slide-up anim-slide-up-2">
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-red-600 text-lg">error</span>
                    </div>
                    <p class="text-sm text-red-600 font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <form class="space-y-5" id="resetForm" method="POST" action="reset_password">
                <div class="input-group relative anim-slide-up anim-slide-up-3">
                    <input class="form-input w-full h-12 px-4 pr-11 rounded-xl bg-gray-50/80 text-sm font-medium text-gray-900" id="password" name="new_password" placeholder=" " required minlength="6" type="password" oninput="checkStrength()"/>
                    <label class="absolute left-4 top-3 text-sm text-gray-400 font-medium" for="password">New password</label>
                    <button class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-600 transition-colors" onclick="togglePw('password', this)" type="button">
                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                    </button>
                </div>

                <!-- Strength -->
                <div class="-mt-2 flex items-center gap-2" id="strengthWrap" style="display:none;">
                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div id="strengthBar" class="strength-bar h-full rounded-full w-0"></div>
                    </div>
                    <span id="strengthLabel" class="text-[11px] font-bold"></span>
                </div>

                <div class="input-group relative anim-slide-up anim-slide-up-3">
                    <input class="form-input w-full h-12 px-4 pr-11 rounded-xl bg-gray-50/80 text-sm font-medium text-gray-900" id="confirm-password" name="confirm_password" placeholder=" " required type="password" oninput="checkMatch()"/>
                    <label class="absolute left-4 top-3 text-sm text-gray-400 font-medium" for="confirm-password">Confirm password</label>
                    <button class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-600 transition-colors" onclick="togglePw('confirm-password', this)" type="button">
                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                    </button>
                </div>
                <p id="matchMsg" class="-mt-3 text-[11px] font-bold hidden"></p>

                <button class="btn-primary w-full h-12 rounded-xl text-white text-sm font-bold tracking-wide flex items-center justify-center gap-2 anim-slide-up anim-slide-up-4" type="submit">
                    Save New Password
                    <span class="material-symbols-outlined text-[18px]">check</span>
                </button>
            </form>

            <p class="text-center text-sm text-gray-400 mt-8 anim-slide-up anim-slide-up-4">
                After saving, you'll be redirected to <a href="/authentication/login" class="font-bold text-brand-blue hover:text-blue-700 transition-colors">sign in</a>
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

    function checkMatch() {
        const pw = document.getElementById('password').value;
        const cpw = document.getElementById('confirm-password').value;
        const msg = document.getElementById('matchMsg');
        if (!cpw) { msg.classList.add('hidden'); return; }
        msg.classList.remove('hidden');
        if (pw === cpw) { msg.textContent = 'Passwords match'; msg.className = 'text-[11px] font-bold text-emerald-500'; }
        else { msg.textContent = 'Passwords do not match'; msg.className = 'text-[11px] font-bold text-red-500'; }
    }

    document.getElementById('password').addEventListener('input', checkMatch);

    document.getElementById('resetForm').addEventListener('submit', function(e) {
        const pw = document.getElementById('password').value;
        const cpw = document.getElementById('confirm-password').value;
        if (pw !== cpw) { e.preventDefault(); alert('Passwords do not match.'); }
    });
</script>
</body>
</html>
