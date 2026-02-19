<?php
http_response_code(404);
session_start();
$logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صفحه یافت نشد | CDNz</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
    <style> html, body, .font-sans { font-family: 'IRANSans', 'IranSans', 'IRANSansWeb', sans-serif !important; } </style>
</head>
<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
    <nav class="border-b border-gray-800 py-3">
        <div class="max-w-3xl mx-auto px-4 flex items-center justify-between">
            <a href="index.php" class="text-2xl font-black">CDNz</a>
            <a href="index.php" class="text-purple-300 hover:text-purple-200">صفحه اصلی</a>
        </div>
    </nav>
    <main class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-md text-center">
            <div class="text-8xl font-extrabold text-gray-600 mb-2">۴۰۴</div>
            <h1 class="text-2xl font-bold mb-2">صفحه یافت نشد</h1>
            <p class="text-gray-400 mb-6">آدرس وارد شده معتبر نیست یا صفحه جابه‌جا شده است.</p>
            <a href="index.php" class="inline-block px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700">بازگشت به صفحه اصلی</a>
            <?php if ($logged_in): ?>
                <a href="dashboard.php" class="inline-block mt-3 mr-3 px-6 py-2 border border-gray-600 rounded-xl text-gray-300 hover:bg-gray-700">داشبورد</a>
            <?php endif; ?>
        </div>
    </main>
    <footer class="border-t border-gray-800 py-4 text-center text-gray-500 text-sm">
        © <?php echo date('Y'); ?> CDNz
    </footer>
</body>
</html>
