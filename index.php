<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CDNz | اولین و سریع‌ترین CDN ایرانی برای کتابخانه‌های وب</title>
    <meta name="description" content="دانلود پرسرعت و پایدار کتابخانه‌های محبوب وب مثل Bootstrap، jQuery، Tailwind و ... از سرورهای داخلی ایران با CDNz.">
    <link rel="canonical" href="https://cdnz.ir/">
    <!-- Site Icon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/uploads/icons/cdnz.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/uploads/icons/cdnz.png">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:title" content="CDNz | اولین و سریع‌ترین CDN ایرانی">
    <meta property="og:description" content="اولین سرویس CDN ایرانی برای دانلود کتابخانه‌های وب با سرعت بالا و تاخیر پایین.">
    <meta property="og:url" content="https://cdnz.ir/">
    <meta property="og:image" content="https://cdnz.ir/assets/og.png">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="CDNz | اولین و سریع‌ترین CDN ایرانی">
    <meta name="twitter:description" content="دانلود پرسرعت کتابخانه‌های وب از سرورهای داخلی.">
    <meta name="twitter:image" content="https://cdnz.ir/assets/og.png">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "CDNz",
      "url": "https://cdnz.ir/",
      "description": "اولین سرویس CDN ایرانی برای کتابخانه‌های محبوب وب",
      "inLanguage": "fa"
    }
    </script>
    <!-- TailwindCSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- IranSans Font -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
    <style>
        html, body, .font-sans {font-family: 'IRANSans', 'IranSans', 'IRANSansWeb', sans-serif !important;}
        img {max-width: 100%; height: auto;}
        .text-xxs{font-size:.68rem;line-height:1rem;}

        /* ---------- Navigation link styles ---------- */
        .nav-link {position: relative; padding-bottom: 4px; color: #d1d5db; transition: color .25s ease;}
        .nav-link:hover {color: #c084fc;}
        .nav-link::after {content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background: #c084fc; transform: scaleX(0); transform-origin: right; transition: transform .3s ease;}
        html[dir='rtl'] .nav-link::after {transform-origin: left;}
        .nav-link:hover::after {transform: scaleX(1);}    

        /* ---------- New visual system ---------- */
        .blur-spot {position: absolute; filter: blur(60px); opacity: .5;}
        .card-glass {background: rgba(31, 41, 55, 0.7); border: 1px solid rgba(148, 163, 184, 0.15); box-shadow: 0 10px 30px rgba(0,0,0,.35); width:100%;}
        .card-hover {transition: transform .25s ease, box-shadow .25s ease, background .25s ease;}
        .card-hover:hover {transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,.45);} 
        .soft-gradient {background-image: linear-gradient(135deg, rgba(168,85,247,.25), rgba(59,130,246,.25));}
        .banner {
            border-radius: 18px; color:#fff; padding: 22px; display:flex; flex-direction:column; justify-content:space-between;
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
        }
        .banner-2 {background: linear-gradient(135deg, #22c55e 0%, #06b6d4 100%);} 
        .banner-3 {background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);} 
        .badge-pill {background: rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); padding:4px 10px; border-radius:9999px; font-size:12px;}
        .break-anywhere{overflow-wrap:anywhere; word-break:break-word;}
        .remember-label{display:inline-flex;align-items:center;gap:.5rem;cursor:pointer;user-select:none}
        .remember-check{appearance:none;-webkit-appearance:none;width:20px;height:20px;border:1.5px solid #a78bfa;border-radius:6px;background:#0b1220;position:relative;transition:all .2s ease;display:inline-block}
        .remember-check:focus{outline:none;box-shadow:0 0 0 3px rgba(139,92,246,.35)}
        .remember-check:checked{background:linear-gradient(135deg,#8b5cf6,#06b6d4);border-color:transparent}
        .remember-check:checked::after{content:'';position:absolute;inset:0;background:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'/></svg>") center/14px 14px no-repeat}
        /* Page effect when mobile menu is open (no shift/translate) */
        #pageWrap{transition: border-radius .35s ease, box-shadow .35s ease;}
        body.menu-open #pageWrap{border-radius:18px; box-shadow:0 25px 70px rgba(0,0,0,.5);}
        /* Removed transform shift so content stays fixed while drawer opens */

        /* Electric SVG/Turbulence border (Business card) */
        :root {--electric-border-color:#8b5cf6;--electric-light-color:oklch(from var(--electric-border-color) l c h);--gradient-color:oklch(from var(--electric-border-color) 0.3 calc(c / 2) h / 0.4);--color-neutral-900:oklch(0.185 0 0);--biz-height:420px}
        .svg-container{position:absolute;width:0;height:0}
        .card-container{padding:2px;border-radius:24px;position:relative;width:100%;background:linear-gradient(-30deg,var(--gradient-color),transparent,var(--gradient-color)),linear-gradient(to bottom,var(--color-neutral-900),var(--color-neutral-900))}
        .inner-container{position:relative}
        .border-outer{border:2px solid rgba(167,139,250,.5);border-radius:24px;padding-right:4px;padding-bottom:4px}
        .main-card{width:100%;height:var(--biz-height);border-radius:24px;border:2px solid var(--electric-border-color);margin-top:-4px;margin-left:-4px;filter:url(#turbulent-displace)}
        .glow-layer-1{border:2px solid rgba(167,139,250,.6);border-radius:24px;position:absolute;top:0;left:0;right:0;bottom:0;filter:blur(1px)}
        .glow-layer-2{border:2px solid var(--electric-light-color);border-radius:24px;position:absolute;top:0;left:0;right:0;bottom:0;filter:blur(4px)}
        .overlay-1{position:absolute;top:0;left:0;right:0;bottom:0;border-radius:24px;opacity:1;mix-blend-mode:overlay;transform:scale(1.1);filter:blur(16px);background:linear-gradient(-30deg,#fff,transparent 30%,transparent 70%,#fff)}
        .overlay-2{position:absolute;top:0;left:0;right:0;bottom:0;border-radius:24px;opacity:.5;mix-blend-mode:overlay;transform:scale(1.1);filter:blur(16px);background:linear-gradient(-30deg,#fff,transparent 30%,transparent 70%,#fff)}
        .background-glow{position:absolute;top:0;left:0;right:0;bottom:0;border-radius:24px;filter:blur(32px);transform:scale(1.1);opacity:.3;z-index:-1;background:linear-gradient(-30deg,var(--electric-light-color),transparent,var(--electric-border-color))}
        .content-container{position:absolute;top:0;left:0;right:0;bottom:0;width:100%;height:100%;display:flex;flex-direction:column}
        .content-top{display:flex;flex-direction:column;padding:24px 24px 8px;height:auto}
        .content-bottom{display:flex;flex-direction:column;padding:8px 24px 24px}
        .scrollbar-glass{background:radial-gradient(47.2% 50% at 50.39% 88.37%,rgba(255,255,255,.12) 0%,rgba(255,255,255,0) 100%),rgba(255,255,255,.04);position:relative;transition:background .3s ease;border-radius:14px;width:fit-content;height:fit-content;padding:10px 20px;text-transform:uppercase;font-weight:bold;font-size:16px;color:rgba(255,255,255,.8)}
        .scrollbar-glass:hover{background:radial-gradient(47.2% 50% at 50.39% 88.37%,rgba(255,255,255,.12) 0%,rgba(255,255,255,0) 100%),rgba(255,255,255,.08)}
        .scrollbar-glass::before{content:"";position:absolute;top:0;left:0;right:0;bottom:0;padding:1px;background:linear-gradient(150deg,rgba(255,255,255,.48) 16.73%,rgba(255,255,255,.08) 30.2%,rgba(255,255,255,.08) 68.2%,rgba(255,255,255,.6) 81.89%);border-radius:inherit;mask:linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);mask-composite:xor;-webkit-mask-composite:xor;pointer-events:none}
        .title{font-size:36px;font-weight:500;margin-top:auto}
        .description{opacity:.5}
        .divider{margin-top:auto;border:none;height:1px;background-color:currentColor;opacity:.1;mask-image:linear-gradient(to right,transparent,black,transparent);-webkit-mask-image:linear-gradient(to right,transparent,black,transparent)}

        /* ---------- Subscription Expiration Animations ---------- */
        .subscription-expiring {
            animation: expiring-pulse 2s ease-in-out infinite;
            border-color: #ef4444 !important;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
        }
        
        .subscription-expiring .glow-layer-1,
        .subscription-expiring .glow-layer-2 {
            border-color: #ef4444 !important;
            animation: expiring-glow 1.5s ease-in-out infinite alternate;
        }
        
        .subscription-expiring .background-glow {
            background: linear-gradient(-30deg, #ef4444, transparent, #dc2626);
            animation: expiring-bg 3s ease-in-out infinite;
        }
        
        .subscription-expiring .scrollbar-glass {
            background: radial-gradient(47.2% 50% at 50.39% 88.37%, rgba(239, 68, 68, 0.3) 0%, rgba(239, 68, 68, 0) 100%), rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            animation: expiring-text 1s ease-in-out infinite alternate;
        }
        
        .subscription-expiring .content-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(239, 68, 68, 0.1) 50%, transparent 70%);
            animation: expiring-sweep 2s ease-in-out infinite;
            pointer-events: none;
            border-radius: 24px;
        }
        
        @keyframes expiring-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        @keyframes expiring-glow {
            0% { opacity: 0.6; filter: blur(1px); }
            100% { opacity: 1; filter: blur(2px); }
        }
        
        @keyframes expiring-bg {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }
        
        @keyframes expiring-text {
            0% { opacity: 0.8; }
            100% { opacity: 1; }
        }
        
        @keyframes expiring-sweep {
            0% { transform: translateX(-100%) translateY(-100%); }
            100% { transform: translateX(100%) translateY(100%); }
        }
        
        /* ---------- Critical Expiration (Less than 7 days) ---------- */
        .subscription-critical {
            animation: critical-warning 1s ease-in-out infinite;
            border-color: #dc2626 !important;
            box-shadow: 0 0 30px rgba(220, 38, 38, 0.5);
        }
        
        .subscription-critical .glow-layer-1,
        .subscription-critical .glow-layer-2 {
            border-color: #dc2626 !important;
            animation: critical-glow 0.8s ease-in-out infinite alternate;
        }
        
        .subscription-critical .background-glow {
            background: linear-gradient(-30deg, #dc2626, transparent, #b91c1c);
            animation: critical-bg 1.5s ease-in-out infinite;
        }
        
        .subscription-critical .scrollbar-glass {
            background: radial-gradient(47.2% 50% at 50.39% 88.37%, rgba(220, 38, 38, 0.4) 0%, rgba(220, 38, 38, 0) 100%), rgba(220, 38, 38, 0.2);
            color: #fecaca;
            animation: critical-text 0.5s ease-in-out infinite alternate;
        }
        
        @keyframes critical-warning {
            0%, 100% { transform: scale(1); }
            25% { transform: scale(1.01) rotate(0.5deg); }
            75% { transform: scale(1.01) rotate(-0.5deg); }
        }
        
        @keyframes critical-glow {
            0% { opacity: 0.8; filter: blur(1px); }
            100% { opacity: 1; filter: blur(3px); }
        }
        
        @keyframes critical-bg {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 0.8; }
        }
        
        @keyframes critical-text {
            0% { opacity: 0.9; }
            100% { opacity: 1; }
        }
    </style>
    <!-- Icons (HeroIcons) -->
    <script src="https://unpkg.com/feather-icons"></script>
    <!-- jQuery CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body class="bg-gray-900 text-white font-sans scroll-smooth overflow-x-hidden">
    <!-- Navigation -->
    <nav class="fixed w-full z-20 top-0 bg-gradient-to-b from-gray-900 to-gray-900 backdrop-blur-md border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-6 md:px-10 py-3 flex items-center justify-between">
            <a href="#home" class="text-3xl font-black tracking-tight">CDNz</a>

            <ul class="hidden md:flex items-center gap-10 rtl:space-x-reverse text-gray-300">
                <li><a href="#features" class="nav-link">ویژگی‌ها</a></li>
                <li><a href="#cdns" class="nav-link">کتابخانه‌ها</a></li>
                <li><a href="#pricing" class="nav-link">تعرفه‌ها</a></li>
                <li><a href="snippets.php" class="nav-link">نمونه‌کدها</a></li>
                <li><a href="#contact" class="nav-link">تماس با ما</a></li>
            </ul>

            <div class="flex items-center gap-3">
                <?php if ($logged_in): ?>
                    <a href="dashboard.php" class="hidden md:inline-flex mx-1.5 items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:to-indigo-700 rounded-full shadow-lg text-white font-semibold transition-all duration-200">داشبورد</a>
                    <a href="api/auth.php?action=logout" class="hidden md:inline-flex mx-1.5 items-center gap-2 px-5 py-2.5 border-2 border-purple-500 text-purple-300 hover:bg-purple-700/20 rounded-full font-semibold transition-colors duration-200">خروج</a>
                <?php else: ?>
                    <button id="openLogin" class="hidden md:inline-flex mx-1.5 items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:to-indigo-700 rounded-full shadow-lg text-white font-semibold transition-all duration-200">ورود</button>
                    <button id="openRegister" class="hidden md:inline-flex mx-1.5 items-center gap-2 px-5 py-2.5 border-2 border-purple-500 text-purple-300 hover:bg-purple-700/20 rounded-full font-semibold transition-colors duration-200">ثبت نام</button>
                <?php endif; ?>
                <!-- Mobile menu button -->
                <button id="mobileMenuBtn" class="md:hidden focus:outline-none">
                    <i data-feather="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </div>
        <!-- Mobile menu -->
        <div id="mobileOverlay" class="fixed inset-0 bg-black/70 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 md:hidden"></div>
        <div id="mobileMenu" class="fixed inset-y-0 right-0 rtl:left-0 rtl:right-auto w-[70vw] sm:w-80 bg-gray-800 border-l border-gray-700 py-4 transform translate-x-full rtl:-translate-x-full transition-transform duration-300 md:hidden z-30 rounded-l-2xl rtl:rounded-r-2xl">
            <a href="#features" class="block px-6 py-2 hover:bg-gray-700">ویژگی‌ها</a>
            <a href="#cdns" class="block px-6 py-2 hover:bg-gray-700">کتابخانه‌ها</a>
            <a href="#pricing" class="block px-6 py-2 hover:bg-gray-700">تعرفه‌ها</a>
            <a href="#contact" class="block px-6 py-2 hover:bg-gray-700">تماس با ما</a>
            <?php if ($logged_in): ?>
                <a href="dashboard.php" class="block mx-4 px-6 py-3 mt-4 bg-gradient-to-r from-purple-500 to-indigo-600 hover:to-indigo-700 rounded-lg font-medium text-white transition">داشبورد</a>
                <a href="api/auth.php?action=logout" class="block mx-4 text-right mt-2 px-6 py-3 border-2 border-purple-500 text-purple-300 hover:bg-purple-700/10 rounded-lg font-medium transition">خروج</a>
            <?php else: ?>
                <button id="mobileLogin" class="block mx-4 text-right px-6 py-3 mt-4 bg-gradient-to-r from-purple-500 to-indigo-600 hover:to-indigo-700 rounded-lg font-medium text-white transition">ورود</button>
                <button id="mobileRegister" class="block mx-4 text-right mt-2 px-6 py-3 border-2 border-purple-500 text-purple-300 hover:bg-purple-700/10 rounded-lg font-medium transition">ثبت نام</button>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Shrinkable Page Wrap -->
    <div id="pageWrap">
    <!-- Hero Section (new) -->
    <section id="home" class="relative pt-28 md:pt-28 overflow-hidden">
        <div class="absolute -top-40 left-1/2 transform -translate-x-1/2 w-72 h-72 sm:w-80 sm:h-80 md:w-96 md:h-96 rounded-full blur-spot pointer-events-none" style="background:radial-gradient(circle,#8b5cf6,transparent 60%)"></div>
        <div class="absolute -bottom-40 right-0 w-64 h-64 sm:w-80 sm:h-80 md:w-[28rem] md:h-[28rem] rounded-full blur-spot pointer-events-none" style="background:radial-gradient(circle,#06b6d4,transparent 60%)"></div>
        <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 md:px-10 overflow-hidden">
            <div class="grid md:grid-cols-12 gap-6 md:gap-8 items-center">
                <div class="md:col-span-7">
                    <span class="inline-flex items-center gap-2 badge-pill mb-4"><i data-feather="zap" class="w-4 h-4"></i> سریع و پایدار</span>
                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold leading-snug mb-6 break-anywhere">
                        اولین و سریع‌ترین <span class="bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-indigo-400">CDN ایرانی</span>
            </h1>
                    <p class="text-gray-300 text-base sm:text-lg md:text-xl max-w-full md:max-w-xl mb-8 break-anywhere px-2 sm:px-1 leading-relaxed">
                        کتابخانه‌های محبوب وب را با تاخیر کم، کش هوشمند و لینک اختصاصی دریافت کنید. کافیست انتخاب کنید و لینک را کپی کنید.
                    </p>
                    <div class="flex items-center gap-3 flex-wrap">
                        <a href="#cdns" class="px-5 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl font-bold hover:from-purple-700 hover:to-indigo-700 shadow-lg">شروع سریع</a>
                        <a href="#features" class="px-5 py-3 bg-gray-800 border border-gray-700 rounded-xl hover:bg-gray-700">ویژگی‌ها</a>
                    </div>
                </div>
                <div class="md:col-span-5 w-full min-w-0">
                    <div class="card-glass soft-gradient rounded-2xl p-4 sm:p-6 md:p-8 card-hover overflow-hidden w-full min-w-0">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-purple-200 font-semibold">لینک اختصاصی</span>
                            <span class="text-xs text-gray-300">Token-secured</span>
                        </div>
                        <div class="bg-gray-900/60 rounded-xl p-3 sm:p-4 border border-gray-700 overflow-x-auto w-full min-w-0" style="-webkit-overflow-scrolling:touch;">
                            <div class="text-xs text-gray-400 mb-2">مثال لینک</div>
                            <div class="relative w-full min-w-0">
                                <code dir="ltr" class="block text-green-300 text-xxs sm:text-xs md:text-base whitespace-nowrap pr-2">https://cdnz.ir/cdn/bootstrap-v5-3-3/bootstrap.min.css?t=USER_TOKEN</code>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-6 w-full">
                            <div class="bg-gray-900/60 rounded-lg p-4 border border-gray-700 text-center">
                                <div class="text-2xl font-extrabold text-purple-400">40+</div>
                                <div class="text-xs text-gray-400 mt-1">کتابخانه</div>
                            </div>
                            <div class="bg-gray-900/60 rounded-lg p-4 border border-gray-700 text-center">
                                <div class="text-2xl font-extrabold text-indigo-400">99.9%</div>
                                <div class="text-xs text-gray-400 mt-1">دسترس‌پذیری</div>
                            </div>
                            <div class="bg-gray-900/60 rounded-lg p-4 border border-gray-700 text-center">
                                <div class="text-2xl font-extrabold text-emerald-400">CDN</div>
                                <div class="text-xs text-gray-400 mt-1">کش فعال</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Promo banners -->
            <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="#pricing" class="banner card-hover min-h-[140px] min-w-0">
                    <span class="badge-pill w-fit">پاداش عضویت</span>
                    <div>
                        <h3 class="text-2xl font-extrabold mt-3">هدیه شروع</h3>
                        <p class="text-sm text-white/90 mt-1">با ثبت‌نام، ۱ گیگابایت پهنای باند رایگان دریافت کنید.</p>
                    </div>
                </a>
                <a href="#cdns" class="banner banner-2 card-hover min-h-[140px] min-w-0">
                    <span class="badge-pill w-fit">کتابخانه‌های محبوب</span>
                    <div>
                        <h3 class="text-2xl font-extrabold mt-3">به‌روزرسانی خودکار</h3>
                        <p class="text-sm text-white/90 mt-1">جدیدترین نسخه‌ها همیشه آمادهٔ استفاده.</p>
                    </div>
                </a>
                <a href="#contact" class="banner banner-3 card-hover min-h-[140px] min-w-0">
                    <span class="badge-pill w-fit">معرفی دوستان</span>
                    <div>
                        <h3 class="text-2xl font-extrabold mt-3">اعتبار هدیه</h3>
                        <p class="text-sm text-white/90 mt-1">با دعوت دوستان اعتبار ترافیک بگیرید.</p>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-16">
        <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 md:px-10 overflow-hidden">
            <div class="grid md:grid-cols-3 gap-6">
                <div class="card-glass rounded-2xl p-8 card-hover">
                    <div class="flex items-center gap-3 mb-3"><i data-feather="zap" class="w-6 h-6 text-purple-400"></i><h3 class="text-lg font-bold">سرعت فوق‌العاده</h3></div>
                    <p class="text-gray-400">شبکه توزیع‌شده و کش چندلایه برای پاسخ‌گویی سریع در سراسر ایران.</p>
                </div>
                <div class="card-glass rounded-2xl p-8 card-hover">
                    <div class="flex items-center gap-3 mb-3"><i data-feather="shield" class="w-6 h-6 text-emerald-400"></i><h3 class="text-lg font-bold">امن و پایدار</h3></div>
                    <p class="text-gray-400">TLS خودکار، توکن لینک اختصاصی و مانیتورینگ ۲۴/۷.</p>
                </div>
                <div class="card-glass rounded-2xl p-8 card-hover">
                    <div class="flex items-center gap-3 mb-3"><i data-feather="refresh-ccw" class="w-6 h-6 text-indigo-400"></i><h3 class="text-lg font-bold">بروزرسانی خودکار</h3></div>
                    <p class="text-gray-400">همیشه آخرین نسخهٔ Bootstrap، jQuery، Tailwind و بیشتر.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CDNs Section -->
    <section id="cdns" class="py-16">
        <div class="max-w-7xl mx-auto px-6 md:px-10">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
                <h2 class="text-3xl md:text-4xl font-extrabold">کتابخانه‌های محبوب</h2>
                <a href="snippets.php" class="text-sm text-purple-300 hover:text-purple-200">نمونه‌کدها</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                <a class="card-glass rounded-xl p-5 card-hover flex flex-col gap-3" href="https://getbootstrap.com/docs/" target="_blank">
                    <div class="flex items-center justify-between">
                        <span class="text-purple-300 text-xs">CSS Framework</span>
                        <span class="badge-pill">Bootstrap</span>
                </div>
                    <img src="https://img.icons8.com/color/96/000000/bootstrap.png" alt="Bootstrap" class="w-16 h-16" loading="lazy"/>
                    <div class="text-sm text-gray-300">نسخه‌های ۴ و ۵ در دسترس</div>
                </a>
                <a class="card-glass rounded-xl p-5 card-hover flex flex-col gap-3" href="https://api.jquery.com/" target="_blank">
                    <div class="flex items-center justify-between">
                        <span class="text-purple-300 text-xs">Utilities</span>
                        <span class="badge-pill">jQuery</span>
                </div>
                    <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/jquery/jquery-original.svg" alt="jQuery" class="w-16 h-16" loading="lazy"/>
                    <div class="text-sm text-gray-300">۳.۷.۱ آخرین نسخه</div>
                </a>
                <a class="card-glass rounded-xl p-5 card-hover flex flex-col gap-3" href="https://tailwindcss.com/docs" target="_blank">
                    <div class="flex items-center justify-between">
                        <span class="text-purple-300 text-xs">CSS</span>
                        <span class="badge-pill">Tailwind</span>
                </div>
                    <img src="https://img.icons8.com/color/96/000000/tailwindcss.png" alt="Tailwind" class="w-16 h-16" loading="lazy"/>
                    <div class="text-sm text-gray-300">نسخه مینیمال آماده</div>
                </a>
                <a class="card-glass rounded-xl p-5 card-hover flex flex-col gap-3" href="https://react.dev/" target="_blank">
                    <div class="flex items-center justify-between">
                        <span class="text-purple-300 text-xs">UI Library</span>
                        <span class="badge-pill">React</span>
                </div>
                    <img src="https://img.icons8.com/color/96/000000/react-native.png" alt="React" class="w-16 h-16" loading="lazy"/>
                    <div class="text-sm text-gray-300">سازگار با Next.js</div>
                </a>
                <a class="card-glass rounded-xl p-5 card-hover flex flex-col gap-3" href="https://vuejs.org/guide/introduction.html" target="_blank">
                    <div class="flex items-center justify-between">
                        <span class="text-purple-300 text-xs">UI Library</span>
                        <span class="badge-pill">Vue</span>
                </div>
                    <img src="https://img.icons8.com/color/96/000000/vue-js.png" alt="Vue" class="w-16 h-16" loading="lazy"/>
                    <div class="text-sm text-gray-300">مستندات رسمی</div>
                </a>
                <a class="card-glass rounded-xl p-5 card-hover flex flex-col gap-3" href="https://angular.io/docs" target="_blank">
                    <div class="flex items-center justify-between">
                        <span class="text-purple-300 text-xs">Framework</span>
                        <span class="badge-pill">Angular</span>
                </div>
                    <img src="https://img.icons8.com/color/96/000000/angularjs.png" alt="Angular" class="w-16 h-16" loading="lazy"/>
                    <div class="text-sm text-gray-300">پشتیبانی نسخه‌های جدید</div>
                </a>
                <a class="card-glass rounded-xl p-5 card-hover flex flex-col gap-3" href="https://nextjs.org/docs" target="_blank">
                    <div class="flex items-center justify-between">
                        <span class="text-purple-300 text-xs">Meta Framework</span>
                        <span class="badge-pill">Next.js</span>
                </div>
                    <img src="https://img.icons8.com/fluency/96/000000/nextjs.png" alt="Next.js" class="w-16 h-16" loading="lazy"/>
                    <div class="text-sm text-gray-300">حالت App Router</div>
                </a>
                <a class="card-glass rounded-xl p-5 card-hover flex flex-col gap-3" href="https://nodejs.org/docs/latest/api/" target="_blank">
                    <div class="flex items-center justify-between">
                        <span class="text-purple-300 text-xs">Runtime</span>
                        <span class="badge-pill">Node.js</span>
                </div>
                    <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/nodejs/nodejs-original.svg" alt="Node.js" class="w-16 h-16" loading="lazy"/>
                    <div class="text-sm text-gray-300">Bundle & Server</div>
                </a>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-16">
        <div class="max-w-7xl mx-auto px-6 md:px-10">
            <h2 class="text-3xl md:text-4xl font-extrabold text-center mb-10">پلن‌ها</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <div class="card-glass rounded-2xl p-8 flex flex-col card-hover">
                    <h3 class="text-xl font-bold mb-2">رایگان</h3>
                    <p class="text-3xl font-extrabold mb-4">0<span class="text-base font-normal"> تومان/ماه</span></p>
                    <ul class="space-y-2 text-gray-300 flex-1">
                        <li>۱ گیگابایت پهنای باند</li>
                        <li>SSL خودکار</li>
                        <li>کتابخانه‌های منتخب</li>
                    </ul>
                    <?php if ($logged_in): ?>
                        <a href="dashboard.php" class="mt-6 px-6 py-2 bg-purple-600 rounded hover:bg-purple-700 text-center block">شروع کنید</a>
                    <?php else: ?>
                        <button type="button" class="pricing-cta mt-6 w-full px-6 py-2 bg-purple-600 rounded hover:bg-purple-700 text-center" data-pricing-cta="register" data-plan="1">شروع کنید</button>
                    <?php endif; ?>
                </div>
                <div class="card-glass rounded-2xl p-8 flex flex-col card-hover">
                    <h3 class="text-xl font-bold mb-2">استاندارد</h3>
                    <p class="text-3xl font-extrabold mb-4">۵۰ هزار<span class="text-base font-normal">/ماه</span></p>
                    <ul class="space-y-2 text-gray-300 flex-1">
                        <li>۱۰ گیگابایت پهنای باند</li>
                        <li>پشتیبانی ایمیلی</li>
                        <li>گزارش‌گیری پایه</li>
                    </ul>
                    <?php if ($logged_in): ?>
                        <a href="pay.php?plan=2" class="mt-6 px-6 py-2 bg-purple-600 rounded hover:bg-purple-700 text-center block">انتخاب پلن</a>
                    <?php else: ?>
                        <button type="button" class="pricing-cta mt-6 w-full px-6 py-2 bg-purple-600 rounded hover:bg-purple-700 text-center" data-pricing-cta="login" data-plan="2">انتخاب پلن</button>
                    <?php endif; ?>
                </div>
                <div class="rounded-2xl p-0 flex flex-col card-hover" style="min-width:0">
                    <main class="main-container">
                      <svg class="svg-container">
                        <defs>
                          <filter id="turbulent-displace" colorInterpolationFilters="sRGB" x="-20%" y="-20%" width="140%" height="140%">
                            <feTurbulence type="turbulence" baseFrequency="0.02" numOctaves="10" result="noise1" seed="1" />
                            <feOffset in="noise1" dx="0" dy="0" result="offsetNoise1">
                              <animate attributeName="dy" values="700; 0" dur="6s" repeatCount="indefinite" calcMode="linear" />
                            </feOffset>
                            <feTurbulence type="turbulence" baseFrequency="0.02" numOctaves="10" result="noise2" seed="1" />
                            <feOffset in="noise2" dx="0" dy="0" result="offsetNoise2">
                              <animate attributeName="dy" values="0; -700" dur="6s" repeatCount="indefinite" calcMode="linear" />
                            </feOffset>
                            <feTurbulence type="turbulence" baseFrequency="0.02" numOctaves="10" result="noise1" seed="2" />
                            <feOffset in="noise1" dx="0" dy="0" result="offsetNoise3">
                              <animate attributeName="dx" values="490; 0" dur="6s" repeatCount="indefinite" calcMode="linear" />
                            </feOffset>
                            <feTurbulence type="turbulence" baseFrequency="0.02" numOctaves="10" result="noise2" seed="2" />
                            <feOffset in="noise2" dx="0" dy="0" result="offsetNoise4">
                              <animate attributeName="dx" values="0; -490" dur="6s" repeatCount="indefinite" calcMode="linear" />
                            </feOffset>
                            <feComposite in="offsetNoise1" in2="offsetNoise2" result="part1" />
                            <feComposite in="offsetNoise3" in2="offsetNoise4" result="part2" />
                            <feBlend in="part1" in2="part2" mode="color-dodge" result="combinedNoise" />
                            <feDisplacementMap in="SourceGraphic" in2="combinedNoise" scale="30" xChannelSelector="R" yChannelSelector="B" />
                          </filter>
                        </defs>
                      </svg>
                      <div class="card-container" style="max-width:100%">
                        <div class="inner-container">
                          <div class="border-outer">
                            <div class="main-card"></div>
                          </div>
                          <div class="glow-layer-1"></div>
                          <div class="glow-layer-2"></div>
                        </div>
                        <div class="overlay-1"></div>
                        <div class="overlay-2"></div>
                        <div class="background-glow"></div>
                        <div class="content-container" style="height:var(--biz-height)">
                          <div class="content-top" style="padding:32px 32px 12px; height:auto">
                            <div class="scrollbar-glass">پرو</div>
                            <p class="text-3xl font-extrabold mb-4" style="margin-top:8px">۷۰ هزار<span class="text-base font-normal">/ماه</span></p>
                          </div>
                          <div class="content-details" style="padding:8px 32px 0;">
                            <p class="description">۱۵ گیگابایت پهنای باند</p>
                            <p class="description">پشتیبانی تیکتی</p>
                            <p class="description">Analytics لحظه‌ای</p>
                          </div>
                          <div class="content-bottom" style="padding:8px 32px 32px; margin-top:auto">
                            <?php if ($logged_in): ?>
                                <a href="pay.php?plan=3" class="mt-4 px-6 py-2 bg-purple-600 rounded hover:bg-purple-700 text-center block">انتخاب پلن</a>
                            <?php else: ?>
                                <button type="button" class="pricing-cta mt-4 w-full px-6 py-2 bg-purple-600 rounded hover:bg-purple-700 text-center" data-pricing-cta="login" data-plan="3">انتخاب پلن</button>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </main>
                </div>
                <div class="card-glass rounded-2xl p-8 flex flex-col card-hover">
                    <h3 class="text-xl font-bold mb-2">بیزینس</h3>
                    <p class="text-3xl font-extrabold mb-4">۱۲۰ هزار<span class="text-base font-normal">/ماه</span></p>
                    <ul class="space-y-2 text-gray-300 flex-1">
                        <li>۳۰ گیگابایت پهنای باند</li>
                        <li>پشتیبانی ۲۴/۷</li>
                        <li>مدیریت اختصاصی</li>
                    </ul>
                    <?php if ($logged_in): ?>
                        <a href="pay.php?plan=4" class="mt-6 px-6 py-2 bg-purple-600 rounded hover:bg-purple-700 text-center block">درخواست دمو</a>
                    <?php else: ?>
                        <button type="button" class="pricing-cta mt-6 w-full px-6 py-2 bg-purple-600 rounded hover:bg-purple-700 text-center" data-pricing-cta="login" data-plan="4">درخواست دمو</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-gradient-to-br from-indigo-900 to-gray-900">
        <div class="max-w-7xl mx-auto px-6 md:px-10 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">در تماس باشید</h2>
            <p class="max-w-xl mx-auto text-gray-300 mb-8">در صورت هرگونه سوال یا نیاز به مشاوره، با ما در ارتباط باشید. تیم ما آماده پاسخ‌گویی به شماست.</p>
            <a href="mailto:support@cdnz.ir" class="inline-block px-8 py-3 bg-purple-600 rounded-lg text-lg font-semibold hover:bg-purple-700 transition">ارسال ایمیل</a>
        </div>
    </section>



    <!-- Footer -->
    <footer class="bg-gray-900 border-t border-gray-700 py-6 text-center text-gray-400">
        <p>© 2025 CDNz. تمامی حقوق محفوظ است.</p>
        <p class="mt-2 text-sm">طراحی و توسعه توسط <a href="https://arvinzax.ir" target="_blank" class="underline hover:text-purple-400">آروین شاه‌پسند</a></p>
    </footer>

    </div><!-- /#pageWrap -->

    <!-- Login Modal (redesigned) -->
    <div id="loginModal" class="fixed inset-0 bg-black/80 backdrop-blur-md flex items-center justify-center hidden modal z-40 px-4" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle" aria-hidden="true">
        <div class="relative w-full max-w-md bg-gray-800 border border-gray-700 rounded-2xl p-6 md:p-8 shadow-2xl">
            <button type="button" class="absolute top-3 left-3 text-gray-300 hover:text-white transition modal-close" aria-label="بستن"><i data-feather="x"></i></button>
            <div class="flex items-center justify-center mb-5">
                <span class="badge-pill">امن و سریع</span>
            </div>
            <h3 id="loginModalTitle" class="text-2xl md:text-3xl font-extrabold text-center mb-2">ورود به حساب</h3>
            <p class="text-center text-gray-300 text-sm mb-6">برای دسترسی به داشبورد و لینک‌های اختصاصی وارد شوید.</p>
            <form id="loginForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="relative">
                    <label class="block text-sm text-gray-300 mb-1">ایمیل</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-3 flex items-center text-gray-400"><i data-feather="mail"></i></span>
                        <input type="email" name="email" placeholder="you@example.com" required class="w-full rounded-xl bg-gray-900/60 border border-gray-700 pr-10 py-3 text-gray-200 placeholder-gray-500 focus:ring-2 focus:ring-purple-600 focus:outline-none">
                    </div>
                </div>
                <div class="relative">
                    <label class="block text-sm text-gray-300 mb-1">رمز عبور</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-3 flex items-center text-gray-400"><i data-feather="lock"></i></span>
                        <input type="password" name="password" placeholder="••••••••" required class="w-full rounded-xl bg-gray-900/60 border border-gray-700 pr-10 py-3 text-gray-200 placeholder-gray-500 focus:ring-2 focus:ring-purple-600 focus:outline-none">
                    </div>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <label class="remember-label text-gray-300"><input type="checkbox" class="remember-check"/> من را به خاطر بسپار</label>
                    <button type="button" id="openReset" class="text-purple-300 hover:text-purple-200">فراموشی رمز عبور؟</button>
                </div>
                <p id="loginError" class="text-red-500 text-sm"></p>
                <button type="submit" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl hover:from-purple-700 hover:to-indigo-700 shadow-lg">ورود</button>
                <div class="text-center text-xs text-gray-400">حساب ندارید؟ <button type="button" id="openRegister" class="text-purple-300 hover:text-purple-200">ثبت نام</button></div>
            </form>
        </div>
    </div>

    <!-- Register Modal (redesigned) -->
    <div id="registerModal" class="fixed inset-0 bg-black/80 backdrop-blur-md flex items-center justify-center hidden modal z-40 px-4" role="dialog" aria-modal="true" aria-labelledby="registerModalTitle" aria-hidden="true">
        <div class="relative w-full max-w-md bg-gray-800 border border-gray-700 rounded-2xl p-6 md:p-8 shadow-2xl">
            <button type="button" class="absolute top-3 left-3 text-gray-300 hover:text-white transition modal-close" aria-label="بستن"><i data-feather="x"></i></button>
            <div class="flex items-center justify-center mb-5">
                <span class="badge-pill">ایجاد حساب</span>
            </div>
            <h3 id="registerModalTitle" class="text-2xl md:text-3xl font-extrabold text-center mb-2">ایجاد حساب جدید</h3>
            <p class="text-center text-gray-300 text-sm mb-6">برای دریافت لینک‌های اختصاصی و مدیریت کتابخانه‌ها ثبت‌نام کنید.</p>
            <form id="registerForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div>
                    <label class="block text-sm text-gray-300 mb-1">نام و نام خانوادگی</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-3 flex items-center text-gray-400"><i data-feather="user"></i></span>
                        <input type="text" name="name" placeholder="مثلاً علی محمدی" required class="w-full rounded-xl bg-gray-900/60 border border-gray-700 pr-10 py-3 text-gray-200 placeholder-gray-500 focus:ring-2 focus:ring-purple-600 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-gray-300 mb-1">ایمیل</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-3 flex items-center text-gray-400"><i data-feather="mail"></i></span>
                        <input type="email" name="email" placeholder="you@example.com" required class="w-full rounded-xl bg-gray-900/60 border border-gray-700 pr-10 py-3 text-gray-200 placeholder-gray-500 focus:ring-2 focus:ring-purple-600 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-gray-300 mb-1">رمز عبور</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-3 flex items-center text-gray-400"><i data-feather="lock"></i></span>
                        <input type="password" name="password" placeholder="••••••••" required class="w-full rounded-xl bg-gray-900/60 border border-gray-700 pr-10 py-3 text-gray-200 placeholder-gray-500 focus:ring-2 focus:ring-purple-600 focus:outline-none">
                    </div>
                </div>
                <p id="registerError" class="text-red-500 text-sm"></p>
                <p id="registerSuccess" class="text-green-500 text-sm"></p>
                <button type="submit" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl hover:from-purple-700 hover:to-indigo-700 shadow-lg">دریافت کد تایید</button>
                <div class="text-center text-xs text-gray-400">قبلاً ثبت‌نام کرده‌اید؟ <button type="button" id="openLogin" class="text-purple-300 hover:text-purple-200">ورود</button></div>
            </form>
            <div id="registerStep2" class="hidden space-y-4 mt-4">
                <div>
                    <label class="block text-sm text-gray-300 mb-1">کد ۶ رقمی</label>
                    <input type="text" id="registerCode" placeholder="123456" class="w-full p-3 rounded-xl bg-gray-900/60 border border-gray-700 text-gray-200 placeholder-gray-500 focus:ring-2 focus:ring-purple-600 focus:outline-none">
                </div>
                <p id="registerMsg" class="text-sm text-red-400"></p>
                <button id="confirmRegister" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl hover:from-purple-700 hover:to-indigo-700 shadow-lg">تایید و ثبت نام</button>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetModal" class="fixed inset-0 bg-black/90 backdrop-blur-lg flex items-center justify-center hidden modal z-40 px-4" role="dialog" aria-modal="true" aria-labelledby="resetModalTitle" aria-hidden="true">
        <div class="relative w-full max-w-lg bg-gray-800 rounded-2xl shadow-2xl ring-1 ring-purple-500/30 p-8">
            <button type="button" class="absolute top-4 left-4 text-gray-400 hover:text-gray-200 transition modal-close" aria-label="بستن"><i data-feather="x"></i></button>
            <h3 id="resetModalTitle" class="text-2xl font-extrabold mb-6 text-center text-purple-400">بازنشانی رمز عبور</h3>
            <input type="hidden" id="resetCsrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div id="resetStep1">
                <input type="email" id="resetEmail" placeholder="ایمیل" class="w-full mb-4 p-3 rounded-lg bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600 focus:outline-none">
                <button id="sendResetCode" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-lg hover:from-purple-700 hover:to-indigo-700 shadow-lg">ارسال کد</button>
            </div>
            <div id="resetStep2" class="hidden">
                <input type="text" id="resetCode" placeholder="کد 6 رقمی" class="w-full mb-4 p-3 rounded-lg bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600 focus:outline-none">
                <input type="password" id="resetNewPass" placeholder="رمز عبور جدید" class="w-full mb-4 p-3 rounded-lg bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600 focus:outline-none">
                <button id="confirmReset" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-lg hover:from-purple-700 hover:to-indigo-700 shadow-lg">تایید و بازنشانی</button>
            </div>
            <p id="resetMsg" class="text-sm mt-4"></p>
        </div>
    </div>

    <!-- App JS -->
    <script src="js/app.js"></script>
    <script>feather.replace();</script>
</body>
</html>
