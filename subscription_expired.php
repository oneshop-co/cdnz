<?php
session_start();
require_once __DIR__ . '/api/db.php';

// Get user ID from URL parameter
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$userId) {
    header('Location: index.php');
    exit;
}

// Get user information
$stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

// Get available plans
$plans = $pdo->query('SELECT * FROM plans WHERE id > 1 ORDER BY price ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اشتراک منقضی شده | CDNz</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        html, body, .font-sans {font-family: 'IRANSans', 'IranSans', 'IRANSansWeb', sans-serif !important;}
        .expired-banner {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            animation: pulse-red 2s ease-in-out infinite;
        }
        @keyframes pulse-red {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        .plan-card {
            background: rgba(31, 41, 55, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.2);
            transition: all 0.3s ease;
        }
        .plan-card:hover {
            transform: translateY(-4px);
            border-color: rgba(139, 92, 246, 0.5);
            box-shadow: 0 20px 40px rgba(139, 92, 246, 0.1);
        }
        .plan-card.featured {
            border-color: #8b5cf6;
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.3);
        }
    </style>
</head>
<body class="bg-gray-900 text-white font-sans min-h-screen">
    <!-- Navigation -->
    <nav class="w-full bg-gray-900/80 backdrop-blur-md border-b border-gray-800 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-6 md:px-10 h-16 flex items-center justify-between">
            <a href="index.php" class="text-2xl font-black">CDNz</a>
            <div class="flex items-center gap-4">
                <span class="text-gray-400"><?= htmlspecialchars($user['name']) ?></span>
                <a href="api/auth.php?action=logout" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm transition">خروج</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 md:px-10 py-8">
        <!-- Expired Banner -->
        <div class="expired-banner rounded-2xl p-8 text-center mb-12">
            <div class="text-6xl mb-4">⚠️</div>
            <h1 class="text-3xl md:text-4xl font-bold mb-4">اشتراک شما منقضی شده است!</h1>
            <p class="text-xl opacity-90 max-w-2xl mx-auto">
                برای ادامه استفاده از سرویس CDNz، لطفاً یکی از پلن‌های زیر را انتخاب کنید.
            </p>
        </div>

        <!-- Plans Section -->
        <section class="mb-12">
            <h2 class="text-2xl md:text-3xl font-bold text-center mb-8">انتخاب پلن جدید</h2>
            
            <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                <?php foreach ($plans as $index => $plan): ?>
                    <?php 
                    $isFeatured = $index === 1; // Standard plan is featured
                    $planClass = $isFeatured ? 'plan-card featured' : 'plan-card';
                    ?>
                    <div class="<?= $planClass ?> rounded-2xl p-6 relative">
                        <?php if ($isFeatured): ?>
                            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                                <span class="bg-gradient-to-r from-purple-500 to-indigo-500 text-white px-4 py-1 rounded-full text-sm font-bold">
                                    پیشنهاد ویژه
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mb-6">
                            <h3 class="text-xl font-bold mb-2"><?= htmlspecialchars($plan['name']) ?></h3>
                            <div class="text-3xl font-bold text-purple-400 mb-1">
                                <?= number_format($plan['price']) ?> ریال
                            </div>
                            <div class="text-gray-400 text-sm">ماهانه</div>
                        </div>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center gap-3">
                                <i data-feather="check" class="w-5 h-5 text-green-400"></i>
                                <span>پهنای باند: <?= formatBytes($plan['limit_bytes']) ?></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i data-feather="check" class="w-5 h-5 text-green-400"></i>
                                <span>دسترسی نامحدود به تمام کتابخانه‌ها</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i data-feather="check" class="w-5 h-5 text-green-400"></i>
                                <span>پشتیبانی ۲۴/۷</span>
                            </div>
                        </div>
                        
                        <a href="pay.php?plan=<?= $plan['id'] ?>&expired=1&user_id=<?= $userId ?>" 
                           class="block w-full text-center py-3 px-6 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 rounded-xl font-semibold transition-all duration-200">
                            انتخاب این پلن
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Additional Info -->
        <section class="text-center text-gray-400 max-w-3xl mx-auto">
            <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                <h3 class="text-lg font-semibold mb-3">سوالات متداول</h3>
                <div class="space-y-3 text-sm">
                    <p>• پس از پرداخت، اشتراک شما بلافاصله فعال می‌شود</p>
                    <p>• امکان تمدید خودکار اشتراک در دسترس است</p>
                    <p>• در صورت بروز مشکل، با پشتیبانی تماس بگیرید</p>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Initialize Feather icons
        feather.replace();
    </script>
</body>
</html>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
