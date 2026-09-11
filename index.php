<?php
session_start();
require_once __DIR__ . '/includes/database_connect.php';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>ShuttleSync | Precision Badminton Management</title>
    <?php include __DIR__ . '/includes/tailwind_config.php'; ?>
    <style>
        /* ============================
           ANIMATIONS & EFFECTS
        ============================ */

        /* Hero animated gradient */
        @keyframes heroShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .hero-bg {
            background: linear-gradient(-45deg, #1a2bc4, #3145e6, #ed4a30, #c93520, #3145e6);
            background-size: 400% 400%;
            animation: heroShift 12s ease infinite;
        }

        /* Floating shapes */
        @keyframes floatUp   { 0%,100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-20px) rotate(8deg); } }
        @keyframes floatDown { 0%,100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(20px) rotate(-8deg); } }
        @keyframes floatSpin { 0%      { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes pulseRing { 0%      { transform: scale(1); opacity: .6; } 100% { transform: scale(1.6); opacity: 0; } }
        @keyframes drift      { 0%,100% { transform: translate(0,0); } 25% { transform: translate(30px, -20px); } 50% { transform: translate(-10px, -40px); } 75% { transform: translate(-30px, -10px); } }

        .float-a { animation: floatUp 4s ease-in-out infinite; }
        .float-b { animation: floatDown 5s ease-in-out infinite; }
        .float-c { animation: floatUp 6s ease-in-out infinite 1s; }
        .float-d { animation: floatDown 4.5s ease-in-out infinite 0.5s; }
        .drift   { animation: drift 15s ease-in-out infinite; }

        /* Scroll reveal */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.7s cubic-bezier(.22,1,.36,1), transform 0.7s cubic-bezier(.22,1,.36,1);
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-left {
            opacity: 0;
            transform: translateX(-60px);
            transition: opacity 0.8s cubic-bezier(.22,1,.36,1), transform 0.8s cubic-bezier(.22,1,.36,1);
        }
        .reveal-left.visible {
            opacity: 1;
            transform: translateX(0);
        }
        .reveal-right {
            opacity: 0;
            transform: translateX(60px);
            transition: opacity 0.8s cubic-bezier(.22,1,.36,1), transform 0.8s cubic-bezier(.22,1,.36,1);
        }
        .reveal-right.visible {
            opacity: 1;
            transform: translateX(0);
        }
        .reveal-scale {
            opacity: 0;
            transform: scale(0.85);
            transition: opacity 0.8s cubic-bezier(.22,1,.36,1), transform 0.8s cubic-bezier(.22,1,.36,1);
        }
        .reveal-scale.visible {
            opacity: 1;
            transform: scale(1);
        }

        /* Stagger children */
        .stagger > * { transition-delay: calc(var(--i, 0) * 0.12s); }

        /* Hero text reveal */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .hero-text-reveal > * {
            opacity: 0;
            animation: slideUp 0.8s cubic-bezier(.22,1,.36,1) forwards;
        }
        .hero-text-reveal > *:nth-child(1) { animation-delay: 0.2s; }
        .hero-text-reveal > *:nth-child(2) { animation-delay: 0.4s; }
        .hero-text-reveal > *:nth-child(3) { animation-delay: 0.6s; }
        .hero-text-reveal > *:nth-child(4) { animation-delay: 0.8s; }
        .hero-text-reveal > *:nth-child(5) { animation-delay: 1.0s; }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #ed4a30 0%, #3145e6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Glow underline */
        .glow-underline {
            position: relative;
            display: inline-block;
        }
        .glow-underline::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 0%;
            height: 4px;
            border-radius: 2px;
            background: linear-gradient(90deg, #ed4a30, #3145e6);
            transition: width 0.4s cubic-bezier(.22,1,.36,1);
        }
        .glow-underline:hover::after {
            width: 100%;
        }

        /* 3D Card tilt */
        .tilt-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            transform-style: preserve-3d;
        }
        .tilt-card:hover {
            box-shadow: 0 25px 60px -12px rgba(49, 69, 230, 0.15);
        }

        /* CTA shimmer */
        @keyframes shimmer {
            0%   { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        .shimmer-btn {
            background: linear-gradient(110deg, #ffffff 30%, #e0e2ec 50%, #ffffff 70%);
            background-size: 200% 100%;
            transition: box-shadow 0.3s;
        }
        .shimmer-btn:hover {
            animation: shimmer 1.5s ease infinite;
            box-shadow: 0 20px 50px -10px rgba(49, 69, 230, 0.35);
        }

        /* Particle canvas */
        #particles-canvas {
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
        }

        /* Smooth scroll */
        html { scroll-behavior: smooth; }

        /* Stats number glow */
        .stat-glow {
            text-shadow: 0 0 40px rgba(237, 74, 48, 0.4);
        }

        /* Nav active pill */
        .nav-pill-active {
            background: linear-gradient(135deg, #3145e6, #1a2bc4);
            color: #fff;
            padding: 6px 16px;
            border-radius: 8px;
        }

        /* Marquee */
        @keyframes marquee {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .marquee-track {
            animation: marquee 25s linear infinite;
        }
    </style>
</head>
<body class="bg-white text-on-background antialiased overflow-x-hidden">

<?php include __DIR__ . '/includes/nav_bar.php'; ?>

<main>

    <!-- ==========================================
         HERO SECTION
    ========================================== -->
    <section class="relative min-h-[750px] flex items-center overflow-hidden">
        <!-- Animated gradient bg -->
        <div class="absolute inset-0 hero-bg"></div>

        <!-- Floating decorative elements -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <!-- Large orbs -->
            <div class="absolute -top-32 -right-32 w-[600px] h-[600px] rounded-full bg-white/5 drift"></div>
            <div class="absolute -bottom-40 -left-40 w-[700px] h-[700px] rounded-full bg-white/5 drift" style="animation-delay: -5s"></div>
            <!-- Geometric shapes -->
            <div class="absolute top-[15%] left-[10%] w-3 h-3 bg-accent rounded-full float-a opacity-60"></div>
            <div class="absolute top-[25%] right-[15%] w-4 h-4 bg-white rounded-full float-b opacity-40"></div>
            <div class="absolute bottom-[30%] left-[20%] w-2 h-2 bg-white rounded-full float-c opacity-50"></div>
            <div class="absolute top-[60%] right-[10%] w-5 h-5 border-2 border-white/30 rounded-full float-d"></div>
            <div class="absolute top-[40%] left-[5%] w-8 h-8 border border-white/20 rounded-lg float-b rotate-45"></div>
            <div class="absolute bottom-[20%] right-[25%] w-6 h-6 border border-accent/30 rounded-full float-a"></div>
            <!-- Shuttlecock-inspired lines -->
            <div class="absolute top-0 right-[20%] w-px h-full bg-gradient-to-b from-transparent via-white/10 to-transparent"></div>
            <div class="absolute top-0 left-[35%] w-px h-full bg-gradient-to-b from-transparent via-white/5 to-transparent"></div>
            <!-- Concentric rings -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[350px] h-[350px] rounded-full border border-white/[0.06] float-c"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[550px] h-[550px] rounded-full border border-white/[0.04] float-d"></div>
            <!-- Animated pulse ring -->
            <div class="absolute top-1/2 left-1/3 -translate-x-1/2 -translate-y-1/2 w-40 h-40 rounded-full border-2 border-accent/20" style="animation: pulseRing 3s ease-in-out infinite;"></div>
        </div>

        <!-- Canvas for floating particles -->
        <canvas id="particles-canvas"></canvas>

        <!-- Content -->
        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-12 w-full">
            <div class="flex flex-col lg:flex-row items-center gap-16">
                <!-- Left: Text -->
                <div class="flex-1 text-center lg:text-left hero-text-reveal">
                    <div class="inline-flex items-center gap-2.5 bg-white/10 backdrop-blur-sm border border-white/15 rounded-full px-5 py-2 mb-8">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-accent"></span>
                        </span>
                        <span class="text-white/80 text-xs font-semibold tracking-[0.2em] uppercase">Smart Badminton Platform</span>
                    </div>

                    <h1 class="text-5xl md:text-6xl lg:text-[76px] font-bold text-white leading-[1.02] tracking-tight mb-6">
                        Elevate Your<br>
                        <span class="relative inline-block">
                            <span class="relative z-10 text-white" style="text-shadow: 0 0 60px rgba(237,74,48,0.5);">Game</span>
                            <span class="absolute -bottom-3 left-0 w-full h-4 bg-accent/40 rounded-full blur-lg"></span>
                        </span>
                        with Intelligence
                    </h1>

                    <p class="text-lg md:text-xl text-white/65 max-w-xl mb-10 leading-relaxed mx-auto lg:mx-0">
                        Book courts, analyze your performance with AI biomechanics, manage reservations, and dominate the court — all from one platform.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <a href="courtbooking" class="group relative bg-accent text-white px-10 py-4.5 rounded-xl text-base font-bold tracking-wide flex items-center justify-center overflow-hidden transition-all hover:shadow-[0_20px_50px_-10px_rgba(237,74,48,0.5)] hover:-translate-y-0.5 active:scale-95">
                            <span class="absolute inset-0 bg-gradient-to-r from-accent-dark to-accent opacity-0 group-hover:opacity-100 transition-opacity"></span>
                            <span class="relative z-10">Book a Court</span>
                        </a>
                        <a href="aimovementanalysis" class="group relative bg-white/10 backdrop-blur-sm border border-white/20 text-white px-10 py-4.5 rounded-xl text-base font-bold hover:bg-white/20 transition-all flex items-center justify-center hover:-translate-y-0.5">
                            Try AI Coach
                        </a>
                    </div>
                </div>

                <!-- Right: Floating stat cards -->
                <div class="flex-shrink-0 hidden lg:grid grid-cols-2 gap-5" style="perspective: 1000px;">
                    <div class="tilt-card bg-white/[0.08] backdrop-blur-md border border-white/15 rounded-2xl p-7 text-center w-48 hover:bg-white/[0.14] transition-all float-a" style="--tilt-x:5deg;--tilt-y:5deg;">
                        <div class="text-4xl font-bold text-white mb-1">6+</div>
                        <div class="text-[11px] text-white/50 font-semibold uppercase tracking-widest">Courts</div>
                    </div>
                    <div class="tilt-card bg-white/[0.08] backdrop-blur-md border border-white/15 rounded-2xl p-7 text-center w-48 hover:bg-white/[0.14] transition-all float-b mt-10" style="--tilt-x:5deg;--tilt-y:5deg;">
                        <div class="text-4xl font-bold text-white mb-1">500+</div>
                        <div class="text-[11px] text-white/50 font-semibold uppercase tracking-widest">Players</div>
                    </div>
                    <div class="tilt-card bg-white/[0.08] backdrop-blur-md border border-white/15 rounded-2xl p-7 text-center w-48 hover:bg-white/[0.14] transition-all float-c" style="--tilt-x:5deg;--tilt-y:5deg;">
                        <div class="text-4xl font-bold text-white mb-1">AI</div>
                        <div class="text-[11px] text-white/50 font-semibold uppercase tracking-widest">Analysis</div>
                    </div>
                    <div class="tilt-card bg-white/[0.08] backdrop-blur-md border border-white/15 rounded-2xl p-7 text-center w-48 hover:bg-white/[0.14] transition-all float-d mt-10" style="--tilt-x:5deg;--tilt-y:5deg;">
                        <div class="text-4xl font-bold text-white mb-1">24/7</div>
                        <div class="text-[11px] text-white/50 font-semibold uppercase tracking-widest">Booking</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scroll indicator -->
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-2 opacity-60">
            <span class="text-[10px] text-white/60 uppercase tracking-[0.3em] font-semibold">Scroll</span>
            <div class="w-5 h-8 border-2 border-white/30 rounded-full flex justify-center pt-1.5">
                <div class="w-1 h-2 bg-white rounded-full animate-bounce"></div>
            </div>
        </div>
    </section>


    <!-- ==========================================
         MARQUEE BANNER
    ========================================== -->
    <div class="bg-[#0f1118] py-4 overflow-hidden border-b border-white/5">
        <div class="flex whitespace-nowrap marquee-track">
            <?php
            $slogans = ['SMASH HARDER', '◆', 'MOVE FASTER', '◆', 'PLAY SMARTER', '◆', 'AI-POWERED', '◆', 'REAL-TIME ANALYSIS', '◆', 'BOOK INSTANTLY', '◆'];
            // duplicate for seamless loop
            $all = array_merge($slogans, $slogans);
            foreach ($all as $s): ?>
                <span class="mx-6 text-sm font-bold tracking-[0.25em] uppercase <?php echo $s === '◆' ? 'text-accent text-xs' : 'text-white/40'; ?>"><?php echo $s; ?></span>
            <?php endforeach; ?>
        </div>
    </div>


    <!-- ==========================================
         FEATURES SECTION
    ========================================== -->
    <section class="py-28 px-6 md:px-12 bg-white relative overflow-hidden">
        <!-- Subtle bg shapes -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary/[0.03] rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-accent/[0.03] rounded-full blur-3xl translate-y-1/2 -translate-x-1/2 pointer-events-none"></div>

        <div class="max-w-7xl mx-auto relative z-10">
            <div class="text-center mb-20 reveal">
                <span class="text-accent text-xs font-bold tracking-[0.25em] uppercase mb-4 block">Features</span>
                <h2 class="text-4xl md:text-5xl font-bold text-on-background tracking-tight mb-4">Everything You Need to <span class="gradient-text">Win</span></h2>
                <p class="text-on-surface-variant max-w-lg mx-auto">One platform to book, play, pay, and improve. Built for serious players.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 stagger">
                <!-- Feature 1 -->
                <a href="courtbooking" class="tilt-card group bg-surface rounded-3xl p-9 border border-outline-variant/40 hover:border-primary/40 transition-all duration-500 hover:-translate-y-2 reveal block" style="--i:0">
                    <h3 class="text-xl font-bold text-on-background mb-3">Court Reservation</h3>
                    <p class="text-on-surface-variant leading-relaxed text-[15px]">
                        Real-time court booking with instant confirmation. Never miss a game with our intelligent scheduling engine.
                    </p>
                    <div class="mt-6 flex items-center gap-2 text-primary text-sm font-bold group-hover:translate-x-1 transition-all duration-300">
                        <span>Book now</span>
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </div>
                </a>

                <!-- Feature 2 -->
                <a href="aimovementanalysis" class="tilt-card group bg-surface rounded-3xl p-9 border border-outline-variant/40 hover:border-accent/40 transition-all duration-500 hover:-translate-y-2 reveal block" style="--i:1">
                    <h3 class="text-xl font-bold text-on-background mb-3">AI Performance Analysis</h3>
                    <p class="text-on-surface-variant leading-relaxed text-[15px]">
                        Real biomechanical feedback powered by MediaPipe. Track form, accuracy, and improve every stroke.
                    </p>
                    <div class="mt-6 flex items-center gap-2 text-accent text-sm font-bold group-hover:translate-x-1 transition-all duration-300">
                        <span>Try it free</span>
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </div>
                </a>

                <!-- Feature 3 -->
                <a href="ecommerce" class="tilt-card group bg-surface rounded-3xl p-9 border border-outline-variant/40 hover:border-primary/40 transition-all duration-500 hover:-translate-y-2 reveal block" style="--i:2">
                    <h3 class="text-xl font-bold text-on-background mb-3">Pro Shop & Payments</h3>
                    <p class="text-on-surface-variant leading-relaxed text-[15px]">
                        Shop gear, pay securely with PayPal, GCash, or at the counter. Transparent transaction history and instant receipts.
                    </p>
                    <div class="mt-6 flex items-center gap-2 text-primary text-sm font-bold group-hover:translate-x-1 transition-all duration-300">
                        <span>Shop now</span>
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </div>
                </a>
            </div>
        </div>
    </section>


    <!-- ==========================================
         WHY CHOOSE SECTION
    ========================================== -->
    <section class="py-28 px-6 md:px-12 bg-surface relative overflow-hidden">
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-primary/[0.03] rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col lg:flex-row gap-16 items-center">
                <!-- Left: Image -->
                <div class="w-full lg:w-1/2 relative reveal-left">
                    <div class="relative rounded-3xl overflow-hidden shadow-2xl group">
                        <img alt="Badminton court" class="w-full h-[440px] object-cover transition-transform duration-700 group-hover:scale-105" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA0w64q4ve5adA4JxgGo-e_7trya5UpRq0F88UqKeDBxXo7BnGhyOVY1oELehOhRqtTFGf01fsfjXXEDLcS18O78pS7bkGGAJ5Ce2DhcRNI7PeMOCNQCQXD7S2p1qBpvFAeV-9lPYcY82BijOC6dSnBaLz0JzDzNFfb7m-D1IPJryco86jv1MxflWAFlVT4hpXRuYa1eo2I3iHL1d4LpyqEv6UBrMsC9pNUgb1q0w9UCpL9CvDgoseYHU7xYgsKTF__1u6W-x-MY0E">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0f1118]/80 via-[#0f1118]/10 to-transparent"></div>
                        <div class="absolute bottom-6 left-6 right-6">
                            <div class="bg-white/10 backdrop-blur-xl rounded-2xl px-6 py-4 border border-white/15">
                                <div class="text-white text-sm font-bold">Trusted by 500+ Players</div>
                                <div class="text-white/50 text-xs">Across the region</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Content -->
                <div class="w-full lg:w-1/2 reveal-right">
                    <span class="text-accent text-xs font-bold tracking-[0.25em] uppercase mb-4 block">Why ShuttleSync</span>
                    <h2 class="text-4xl md:text-5xl font-bold text-on-background tracking-tight mb-12">Built for Players,<br>Designed for <span class="gradient-text">Winners</span></h2>

                    <div class="space-y-8">
                        <div class="flex items-start gap-5 group/item">
                            <div>
                                <h4 class="text-base font-bold text-on-background mb-1">Centralized Management</h4>
                                <p class="text-sm text-on-surface-variant leading-relaxed">Court schedules, player analytics, and payments — all in one control center.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-5 group/item">
                            <div>
                                <h4 class="text-base font-bold text-on-background mb-1">AI-Powered Coaching</h4>
                                <p class="text-sm text-on-surface-variant leading-relaxed">Real biomechanical analysis using MediaPipe pose detection — not random scores.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-5 group/item">
                            <div>
                                <h4 class="text-base font-bold text-on-background mb-1">Real-Time Reservations</h4>
                                <p class="text-sm text-on-surface-variant leading-relaxed">Live updates across all devices. Zero double-bookings, zero confusion.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-5 group/item">
                            <div>
                                <h4 class="text-base font-bold text-on-background mb-1">Secure Digital Payments</h4>
                                <p class="text-sm text-on-surface-variant leading-relaxed">PayPal, GCash, and cash — every transaction is protected.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- ==========================================
         STATS BAR
    ========================================== -->
    <section class="py-20 bg-[#0f1118] relative overflow-hidden" id="statsSection">
        <div class="absolute inset-0">
            <div class="absolute top-1/2 left-1/4 w-96 h-96 bg-primary/10 rounded-full blur-3xl -translate-y-1/2"></div>
            <div class="absolute top-1/2 right-1/4 w-72 h-72 bg-accent/10 rounded-full blur-3xl -translate-y-1/2"></div>
            <!-- Grid lines -->
            <div class="absolute inset-0 opacity-[0.03]" style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 60px 60px;"></div>
        </div>
        <div class="max-w-7xl mx-auto px-6 md:px-12 relative z-10">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center stagger">
                <div class="reveal" style="--i:0">
                    <div class="text-5xl md:text-6xl font-bold text-white mb-3 stat-glow counter" data-target="6" data-suffix="+">0+</div>
                    <div class="text-[11px] text-white/40 font-bold uppercase tracking-[0.2em]">Badminton Courts</div>
                </div>
                <div class="reveal" style="--i:1">
                    <div class="text-5xl md:text-6xl font-bold text-accent mb-3 counter" data-target="500" data-suffix="+">0+</div>
                    <div class="text-[11px] text-white/40 font-bold uppercase tracking-[0.2em]">Active Players</div>
                </div>
                <div class="reveal" style="--i:2">
                    <div class="text-5xl md:text-6xl font-bold text-white mb-3 counter" data-target="10000" data-suffix="+">0+</div>
                    <div class="text-[11px] text-white/40 font-bold uppercase tracking-[0.2em]">Sessions Booked</div>
                </div>
                <div class="reveal" style="--i:3">
                    <div class="text-5xl md:text-6xl font-bold text-accent mb-3 stat-glow">24/7</div>
                    <div class="text-[11px] text-white/40 font-bold uppercase tracking-[0.2em]">Online Booking</div>
                </div>
            </div>
        </div>
    </section>


    <!-- ==========================================
         CTA SECTION
    ========================================== -->
    <section class="py-28 px-6 md:px-12 bg-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto relative reveal-scale">
            <div class="hero-bg rounded-[2rem] p-14 md:p-24 text-center relative overflow-hidden shadow-2xl">
                <!-- Decorative elements -->
                <div class="absolute inset-0 overflow-hidden pointer-events-none">
                    <div class="absolute -top-20 -right-20 w-80 h-80 bg-white/[0.08] rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-accent/20 rounded-full blur-3xl"></div>
                    <div class="absolute top-[20%] left-[10%] w-20 h-20 rounded-full border border-white/10 float-a"></div>
                    <div class="absolute bottom-[20%] right-[15%] w-28 h-28 rounded-full border border-white/10 float-b"></div>
                    <div class="absolute top-[50%] left-[50%] -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] rounded-full border border-white/[0.05]"></div>
                    <!-- Grid -->
                    <div class="absolute inset-0 opacity-[0.04]" style="background-image: radial-gradient(rgba(255,255,255,0.8) 1px, transparent 1px); background-size: 24px 24px;"></div>
                </div>

                <div class="relative z-10">
                    <h2 class="text-4xl md:text-[52px] font-bold text-white tracking-tight mb-6 leading-tight">Ready to Dominate<br>the Court?</h2>
                    <p class="text-lg text-white/65 mb-12 max-w-2xl mx-auto leading-relaxed">
                        Join hundreds of players already using ShuttleSync to book courts, analyze form, and level up their game.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="/authentication/register" class="shimmer-btn text-primary px-12 py-5 rounded-xl font-bold text-base inline-flex items-center justify-center hover:-translate-y-0.5">
                            Get Started Free
                        </a>
                        <a href="courtbooking" class="bg-white/15 border border-white/25 text-white px-12 py-5 rounded-xl font-bold text-base hover:bg-white/25 transition-all inline-flex items-center justify-center gap-2 hover:-translate-y-0.5">
                            Browse Courts
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php include __DIR__ . '/includes/mobile_nav.php'; ?>

<script>
// ==========================================
// SCROLL REVEAL (IntersectionObserver)
// ==========================================
const revealElements = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });
revealElements.forEach(el => observer.observe(el));


// ==========================================
// ANIMATED COUNTERS
// ==========================================
const counters = document.querySelectorAll('.counter');
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !entry.target.dataset.counted) {
            entry.target.dataset.counted = 'true';
            const target = parseInt(entry.target.dataset.target);
            const suffix = entry.target.dataset.suffix || '';
            const duration = 2000;
            const start = performance.now();

            function update(now) {
                const elapsed = now - start;
                const progress = Math.min(elapsed / duration, 1);
                // easeOutExpo
                const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                const current = Math.floor(eased * target);
                entry.target.textContent = current.toLocaleString() + suffix;
                if (progress < 1) requestAnimationFrame(update);
            }
            requestAnimationFrame(update);
        }
    });
}, { threshold: 0.5 });
counters.forEach(el => counterObserver.observe(el));


// ==========================================
// 3D TILT EFFECT ON CARDS
// ==========================================
document.querySelectorAll('.tilt-card').forEach(card => {
    card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const rotateX = ((y - centerY) / centerY) * -6;
        const rotateY = ((x - centerX) / centerX) * 6;
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02,1.02,1.02)`;
    });
    card.addEventListener('mouseleave', () => {
        card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1,1,1)';
    });
});


// ==========================================
// FLOATING PARTICLES (Canvas)
// ==========================================
(function() {
    const canvas = document.getElementById('particles-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const particles = [];
    const PARTICLE_COUNT = 40;

    function resize() {
        canvas.width = canvas.parentElement.offsetWidth;
        canvas.height = canvas.parentElement.offsetHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    for (let i = 0; i < PARTICLE_COUNT; i++) {
        particles.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            r: Math.random() * 2 + 0.5,
            dx: (Math.random() - 0.5) * 0.5,
            dy: (Math.random() - 0.5) * 0.5,
            opacity: Math.random() * 0.3 + 0.1
        });
    }

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255,255,255,${p.opacity})`;
            ctx.fill();
            p.x += p.dx;
            p.y += p.dy;
            if (p.x < 0 || p.x > canvas.width) p.dx *= -1;
            if (p.y < 0 || p.y > canvas.height) p.dy *= -1;
        });
        // Draw faint connections
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const dist = Math.hypot(particles[i].x - particles[j].x, particles[i].y - particles[j].y);
                if (dist < 120) {
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.strokeStyle = `rgba(255,255,255,${0.06 * (1 - dist / 120)})`;
                    ctx.lineWidth = 0.5;
                    ctx.stroke();
                }
            }
        }
        requestAnimationFrame(draw);
    }
    draw();
})();
</script>

</body>
</html>