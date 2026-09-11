<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../includes/database_connect.php';

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    $turnstile_response = $_POST['cf-turnstile-response'] ?? '';
    $turnstile_secret = '0x4AAAAAADg0SSk0Zg_IMnr63V8nxL8AMMk';

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'secret'   => $turnstile_secret,
            'response' => $turnstile_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]
    ]);
    $captcha_result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($email)) {
        $error_message = "Please enter your email address.";
    } elseif (empty($turnstile_response)) {
        $error_message = "Turnstile token is empty. The widget did not load properly on the page.";
    } elseif (!$captcha_result['success']) {
        $cf_error = isset($captcha_result['error-codes']) ? implode(', ', $captcha_result['error-codes']) : 'Unknown Cloudflare Error';
        $error_message = "Turnstile Error: " . $cf_error;
    } else {
        $stmt = $pdo->prepare("SELECT user_id, full_name FROM Users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $reset_code = random_int(100000, 999999);
            $expires = date('Y-m-d H:i:s', time() + 900);

            $update_stmt = $pdo->prepare("UPDATE Users SET reset_code = :code, code_expires = :expires WHERE email = :email");
            $update_stmt->execute([
                'code' => $reset_code,
                'expires' => $expires,
                'email' => $email
            ]);

            $_SESSION['reset_email'] = $email;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'yann.senar123@gmail.com';
                $mail->Password   = 'reem dcsy qgvz aadl';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('hipowerbc@gmail.com', 'ShuttleSync Admin');
                $mail->addAddress($email, $user['full_name']);

                $mail->isHTML(true);
                $mail->Subject = 'ShuttleSync - Password Reset Code';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; color: #0b1c30;'>
                        <h2 style='color: #3145e6;'>Password Reset Request</h2>
                        <p>Hello <b>" . htmlspecialchars($user['full_name']) . "</b>,</p>
                        <p>Your 6-digit verification code is:</p>
                        <h1 style='letter-spacing: 5px; color: #3145e6;'>" . $reset_code . "</h1>
                        <p>This code will expire in 15 minutes.</p>
                        <p>If you did not request this, please ignore this email.</p>
                    </div>
                ";
                $mail->AltBody = "Hello " . $user['full_name'] . ",\n\nYour code is: " . $reset_code . "\n\nExpires in 15 minutes.";
                $mail->send();
                header("Location: /authentication/verify_code");
                exit;
            } catch (Exception $e) {
                $error_message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error_message = "If this email is registered, a code has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Forgot Password | ShuttleSync</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
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
                No worries,<br>
                <span class="bg-gradient-to-r from-blue-400 to-cyan-400 bg-clip-text text-transparent">we've got you.</span>
            </h1>

            <p class="text-lg text-blue-100/70 leading-relaxed mb-10 max-w-md anim-slide-up anim-slide-up-3">
                We'll send a verification code to your registered email so you can reset your password in seconds.
            </p>

            <div class="flex gap-6 anim-slide-up anim-slide-up-4">
                <div class="flex items-center gap-2 text-blue-100/50 text-sm">
                    <span class="material-symbols-outlined text-blue-400 text-lg">timer</span>
                    15 min expiry
                </div>
                <div class="flex items-center gap-2 text-blue-100/50 text-sm">
                    <span class="material-symbols-outlined text-blue-400 text-lg">verified</span>
                    Secure code
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
                <div class="w-14 h-14 rounded-2xl bg-brand-blue/10 flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-brand-blue text-[28px]">mail</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Forgot password?</h2>
                <p class="text-gray-400 text-[15px] mb-8">Enter your email and we'll send you a 6-digit reset code</p>
            </div>

            <?php if ($error_message): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 anim-slide-up anim-slide-up-2">
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-red-600 text-lg">error</span>
                    </div>
                    <p class="text-sm text-red-600 font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <form class="space-y-5" method="POST" action="forgot_password">
                <div class="input-group relative anim-slide-up anim-slide-up-3">
                    <input class="form-input w-full h-12 px-4 rounded-xl bg-gray-50/80 text-sm font-medium text-gray-900" id="email" name="email" placeholder=" " required type="email"/>
                    <label class="absolute left-4 top-3 text-sm text-gray-400 font-medium" for="email">Email address</label>
                </div>

                <div class="flex justify-center py-1 anim-slide-up anim-slide-up-3">
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAADg0SdY7FAjShZ-i" data-theme="light"></div>
                </div>

                <button class="btn-primary w-full h-12 rounded-xl text-white text-sm font-bold tracking-wide flex items-center justify-center gap-2 anim-slide-up anim-slide-up-4" type="submit">
                    Send Reset Code
                    <span class="material-symbols-outlined text-[18px]">send</span>
                </button>
            </form>

            <p class="text-center text-sm text-gray-400 mt-8 anim-slide-up anim-slide-up-4">
                Remember your password? <a href="/authentication/login" class="font-bold text-brand-blue hover:text-blue-700 transition-colors">Sign in</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
