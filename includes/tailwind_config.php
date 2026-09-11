<?php
// includes/tailwind_config.php
// Single source of truth for Tailwind CSS configuration
?>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&display=swap" rel="stylesheet">
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                "colors": {
                    "primary": "#3145e6",
                    "primary-light": "#5b6ef0",
                    "primary-dark": "#1a2bc4",
                    "accent": "#ed4a30",
                    "accent-light": "#f0725c",
                    "accent-dark": "#c93520",
                    "on-primary": "#ffffff",
                    "on-accent": "#ffffff",
                    "background": "#ffffff",
                    "surface": "#f8f9fc",
                    "surface-dim": "#f0f1f5",
                    "surface-container": "#e8eaf0",
                    "surface-container-low": "#f2f3f7",
                    "surface-container-lowest": "#ffffff",
                    "surface-container-high": "#dddee6",
                    "surface-container-highest": "#d1d3dc",
                    "surface-bright": "#ffffff",
                    "surface-variant": "#e0e2ec",
                    "on-surface": "#0f1118",
                    "on-surface-variant": "#44475a",
                    "on-background": "#0f1118",
                    "outline": "#727588",
                    "outline-variant": "#c4c6d0",
                    "error": "#d32f2f",
                    "error-container": "#fdecea",
                    "on-error": "#ffffff",
                    "on-error-container": "#8a1c13",
                    "primary-container": "#3145e6",
                    "on-primary-container": "#ffffff"
                },
                "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                "spacing": { "base": "8px", "margin-md": "32px", "gutter": "24px", "margin-sm": "16px", "container-max": "1280px", "margin-lg": "64px" },
                "fontFamily": {
                    "headline-md": ["Geist", "sans-serif"], "headline-xl": ["Geist", "sans-serif"],
                    "body-md": ["Geist", "sans-serif"], "headline-lg-mobile": ["Geist", "sans-serif"],
                    "headline-lg": ["Geist", "sans-serif"], "body-lg": ["Geist", "sans-serif"],
                    "label-sm": ["Geist", "sans-serif"], "label-md": ["Geist", "sans-serif"]
                },
                "fontSize": {
                    "headline-md": ["24px", {"lineHeight": "1.3", "letterSpacing": "-0.02em", "fontWeight": "600"}],
                    "headline-xl": ["48px", {"lineHeight": "1.1", "letterSpacing": "-0.04em", "fontWeight": "700"}],
                    "body-md": ["16px", {"lineHeight": "1.5", "letterSpacing": "0", "fontWeight": "400"}],
                    "headline-lg-mobile": ["24px", {"lineHeight": "1.2", "letterSpacing": "-0.02em", "fontWeight": "600"}],
                    "headline-lg": ["32px", {"lineHeight": "1.2", "letterSpacing": "-0.03em", "fontWeight": "600"}],
                    "body-lg": ["18px", {"lineHeight": "1.6", "letterSpacing": "-0.01em", "fontWeight": "400"}],
                    "label-sm": ["12px", {"lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "600"}],
                    "label-md": ["14px", {"lineHeight": "1.4", "letterSpacing": "0.01em", "fontWeight": "500"}]
                }
            }
        }
    }
</script>
<style>
    body { font-family: 'Geist', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(226, 232, 240, 0.8); }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #3145e6; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .shadow-kinetic { box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.05); }
    .radial-gauge { position: relative; width: 120px; height: 120px; border-radius: 50%; }
    .radial-gauge::before { content: ''; position: absolute; top: 12px; left: 12px; right: 12px; bottom: 12px; background: #ffffff; border-radius: 50%; z-index: 1; }
    .hero-gradient { background: linear-gradient(135deg, #3145e6 0%, #1a2bc4 40%, #ed4a30 100%); }
    .btn-primary { background: #3145e6; color: #fff; transition: all 0.2s; }
    .btn-primary:hover { background: #1a2bc4; transform: translateY(-1px); box-shadow: 0 8px 25px rgba(49, 69, 230, 0.35); }
    .btn-accent { background: #ed4a30; color: #fff; transition: all 0.2s; }
    .btn-accent:hover { background: #c93520; transform: translateY(-1px); box-shadow: 0 8px 25px rgba(237, 74, 48, 0.35); }
    @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } }
    .animate-float { animation: float 3s ease-in-out infinite; }
    .shuttlecock-shadow { filter: drop-shadow(0 20px 40px rgba(49, 69, 230, 0.3)); }
</style>
