<?php
session_start();
$logged_in = isset($_SESSION['user_id']);
$status = $_GET['status'] ?? '';
$renewed = isset($_GET['renewed']) && $_GET['renewed'] == '1';
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$code = (int)($_GET['code'] ?? 0);

$success = ($status === 'ok');
$cancel = ($status === 'cancel');
$title = $success ? 'پرداخت موفق' : ($cancel ? 'لغو پرداخت' : 'خطا در پرداخت');
$message = '';
if ($success) {
    $message = $renewed ? 'اشتراک شما با موفقیت تمدید شد.' : 'پرداخت با موفقیت انجام شد و پلن شما فعال گردید.';
} elseif ($cancel) {
    $message = 'پرداخت توسط شما لغو شد.';
} else {
    $message = $msg !== '' ? $msg : 'در تأیید پرداخت خطایی رخ داده است. در صورت کسر مبلغ، با پشتیبانی تماس بگیرید.';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> | CDNz</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
    <style> html, body, .font-sans { font-family: 'IRANSans', 'IranSans', 'IRANSansWeb', sans-serif !important; } </style>
</head>
<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
    <nav class="border-b border-gray-800 py-3">
        <div class="max-w-3xl mx-auto px-4 flex items-center justify-between">
            <a href="index.php" class="text-2xl font-black">CDNz</a>
            <?php if ($logged_in): ?>
                <a href="dashboard.php" class="text-purple-300 hover:text-purple-200">داشبورد</a>
            <?php else: ?>
                <a href="index.php" class="text-purple-300 hover:text-purple-200">صفحه اصلی</a>
            <?php endif; ?>
        </div>
    </nav>
    <main class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-md bg-gray-800 border border-gray-700 rounded-2xl p-8 text-center shadow-xl">
            <?php if ($success): ?>
                <div class="mx-auto w-16 h-16 rounded-full bg-green-500/20 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            <?php elseif ($cancel): ?>
                <div class="mx-auto w-16 h-16 rounded-full bg-yellow-500/20 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            <?php else: ?>
                <div class="mx-auto w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            <?php endif; ?>
            <h1 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($title); ?></h1>
            <p class="text-gray-300 mb-6"><?php echo htmlspecialchars($message); ?></p>
            <?php if ($logged_in): ?>
                <a href="dashboard.php<?php echo $renewed ? '?renewed=1' : ''; ?>" class="inline-block px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700">رفتن به داشبورد</a>
            <?php endif; ?>
            <a href="index.php#pricing" class="inline-block mt-3 px-6 py-2 border border-gray-600 rounded-xl text-gray-300 hover:bg-gray-700"><?php echo $logged_in ? 'بازگشت به تعرفه‌ها' : 'صفحه اصلی'; ?></a>
        </div>
    </main>
    <footer class="border-t border-gray-800 py-4 text-center text-gray-500 text-sm">
        © <?php echo date('Y'); ?> CDNz
    </footer>
</body>
</html>
