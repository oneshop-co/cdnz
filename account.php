<?php
session_start();
if(!isset($_SESSION['user_id'])){ header('Location: index.php'); exit; }
require_once __DIR__.'/api/db.php';

// بررسی دسترسی کاربر
$freeUsed = $pdo->prepare('SELECT id FROM free_plan_used WHERE user_id = ?');
$freeUsed->execute([$_SESSION['user_id']]);

if ($freeUsed->fetch()) {
    // کاربر قبلاً از پلن رایگان استفاده کرده، بررسی اشتراک فعال
    $now = date('Y-m-d H:i:s');
    $activeSub = $pdo->prepare('SELECT s.expires_at FROM user_subscriptions s 
                               WHERE s.user_id = ? AND s.expires_at > ? 
                               ORDER BY s.expires_at DESC LIMIT 1');
    $activeSub->execute([$_SESSION['user_id'], $now]);
    
    if (!$activeSub->fetch()) {
        // اشتراک منقضی شده، هدایت به صفحه پرداخت
        header('Location: subscription_expired.php?user_id=' . $_SESSION['user_id']);
        exit;
    }
}

$userId=(int)$_SESSION['user_id'];
$stmt=$pdo->prepare('SELECT name,email FROM users WHERE id=?');
$stmt->execute([$userId]);
$u=$stmt->fetch();

if($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['name']??'');
  $email=trim($_POST['email']??'');
  if($name && filter_var($email,FILTER_VALIDATE_EMAIL)){
    $upd=$pdo->prepare('UPDATE users SET name=?, email=? WHERE id=?');
    $upd->execute([$name,$email,$userId]);
    header('Location: account.php?ok=1'); exit;
  }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>اطلاعات حساب | CDNz</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
  <style>html,body,.font-sans{font-family:'IRANSans','IranSans','IRANSansWeb',sans-serif!important}</style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
  <nav class="w-full bg-gray-900/80 backdrop-blur-md border-b border-gray-800 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-6 md:px-10 h-14 flex items-center justify-between">
      <a href="dashboard.php" class="text-purple-300 hover:text-purple-200">← داشبورد</a>
      <span class="font-black">اطلاعات حساب</span>
      <a href="api/auth.php?action=logout" class="px-3 py-1.5 bg-purple-600 rounded hover:bg-purple-700 text-sm">خروج</a>
    </div>
  </nav>

  <main class="max-w-2xl mx-auto px-6 md:px-10 py-8">
    <h1 class="text-2xl md:text-3xl font-extrabold mb-6">ویرایش اطلاعات</h1>
    <?php if(isset($_GET['ok'])): ?><div class="mb-4 text-green-400">ذخیره شد.</div><?php endif; ?>
    <form method="POST" class="space-y-4 bg-gray-800 p-6 rounded-xl border border-gray-700">
      <div>
        <label class="block text-sm text-gray-300 mb-1">نام و نام خانوادگی</label>
        <input name="name" value="<?php echo htmlspecialchars($u['name']??''); ?>" class="w-full rounded-xl bg-gray-900/60 border border-gray-700 px-3 py-3 text-gray-200 focus:ring-2 focus:ring-purple-600"/>
      </div>
      <div>
        <label class="block text-sm text-gray-300 mb-1">ایمیل</label>
        <input name="email" value="<?php echo htmlspecialchars($u['email']??''); ?>" class="w-full rounded-xl bg-gray-900/60 border border-gray-700 px-3 py-3 text-gray-200 focus:ring-2 focus:ring-purple-600"/>
      </div>
      <div class="flex justify-end gap-3">
        <a href="dashboard.php" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 text-sm">انصراف</a>
        <button class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 rounded hover:from-purple-700 hover:to-indigo-700">ذخیره</button>
      </div>
    </form>
  </main>
</body>
</html>


