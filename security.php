<?php
session_start();
if(isset($_GET['debug'])){ ini_set('display_errors','1'); error_reporting(E_ALL); }
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
$msg='';

// ensure structures
try{$pdo->query("SELECT login_notify FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN login_notify TINYINT(1) NOT NULL DEFAULT 0");}
$pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, ip VARCHAR(64) NULL, user_agent VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");

// handlers
if(isset($_POST['action']) && $_POST['action']==='change_password'){
  $cur=$_POST['current']??''; $new=$_POST['new']??''; $rep=$_POST['repeat']??'';
  if($new && $new===$rep){
    $st=$pdo->prepare('SELECT password FROM users WHERE id=?'); $st->execute([$userId]); $hash=$st->fetchColumn();
    if(!$hash || !password_verify($cur,$hash)){ $msg='رمز عبور فعلی نادرست است.'; }
    else{
      $newHash=password_hash($new,PASSWORD_BCRYPT); $up=$pdo->prepare('UPDATE users SET password=? WHERE id=?'); $up->execute([$newHash,$userId]); $msg='رمز عبور با موفقیت تغییر کرد.';
    }
  } else { $msg='رمز جدید و تکرار آن یکسان نیست.'; }
}

if(isset($_POST['action']) && $_POST['action']==='set_notify'){
  $val=(int)($_POST['login_notify']??0)===1?1:0;
  $pdo->prepare('UPDATE users SET login_notify=? WHERE id=?')->execute([$val,$userId]);
  $msg='تنظیمات اعلان ورود ذخیره شد.';
}

if(isset($_POST['action']) && $_POST['action']==='clear_sessions'){
  $pdo->prepare('DELETE FROM user_sessions WHERE user_id=?')->execute([$userId]);
  $msg='نشست‌ها پاک‌سازی شد.';
}

// Sessions (simple device list: latest 10)
$sessions=$pdo->prepare('SELECT id, ip, created_at FROM user_sessions WHERE user_id=? ORDER BY created_at DESC LIMIT 10');
$sessionsRows=[];
try{ $sessions->execute([$userId]); $sessionsRows=$sessions->fetchAll(PDO::FETCH_ASSOC); }catch(Exception $e){ $sessionsRows=[]; }
// current notify
$notify=$pdo->prepare('SELECT login_notify FROM users WHERE id=?');
$notify->execute([$userId]);
$loginNotify=(int)$notify->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>امنیت | CDNz</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
  <style>html,body,.font-sans{font-family:'IRANSans','IranSans','IRANSansWeb',sans-serif!important}</style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
  <nav class="w-full bg-gray-900/80 backdrop-blur-md border-b border-gray-800 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-6 md:px-10 h-14 flex items-center justify-between">
      <a href="dashboard.php" class="text-purple-300 hover:text-purple-200">← داشبورد</a>
      <span class="font-black">امنیت</span>
      <a href="api/auth.php?action=logout" class="px-3 py-1.5 bg-purple-600 rounded hover:bg-purple-700 text-sm">خروج</a>
    </div>
  </nav>

  <main class="max-w-3xl mx-auto px-6 md:px-10 py-8 space-y-6">
    <?php if($msg): ?><div class="bg-gray-800 border border-gray-700 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <section class="bg-gray-800 rounded-xl p-6 border border-gray-700">
      <h2 class="text-xl font-bold mb-4">تغییر رمز عبور</h2>
      <form method="POST" class="grid sm:grid-cols-2 gap-4">
        <input type="hidden" name="action" value="change_password"/>
        <div class="sm:col-span-2">
          <label class="block text-sm text-gray-300 mb-1">رمز فعلی</label>
          <input name="current" type="password" class="w-full rounded-xl bg-gray-900/60 border border-gray-700 px-3 py-3 text-gray-200 focus:ring-2 focus:ring-purple-600"/>
        </div>
        <div>
          <label class="block text-sm text-gray-300 mb-1">رمز جدید</label>
          <input name="new" type="password" class="w-full rounded-xl bg-gray-900/60 border border-gray-700 px-3 py-3 text-gray-200 focus:ring-2 focus:ring-purple-600"/>
        </div>
        <div>
          <label class="block text-sm text-gray-300 mb-1">تکرار رمز جدید</label>
          <input name="repeat" type="password" class="w-full rounded-xl bg-gray-900/60 border border-gray-700 px-3 py-3 text-gray-200 focus:ring-2 focus:ring-purple-600"/>
        </div>
        <div class="sm:col-span-2 flex justify-end gap-3">
          <a href="dashboard.php" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 text-sm">انصراف</a>
          <button class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 rounded hover:from-purple-700 hover:to-indigo-700">ذخیره</button>
        </div>
      </form>
    </section>

    <section class="bg-gray-800 rounded-xl p-6 border border-gray-700">
      <h2 class="text-xl font-bold mb-4">اعلان‌های امنیتی</h2>
      <form method="POST" class="flex items-center justify-between gap-4">
        <input type="hidden" name="action" value="set_notify"/>
        <label class="flex items-center gap-2 text-sm text-gray-300">
          <input type="checkbox" name="login_notify" value="1" <?php echo $loginNotify? 'checked':''; ?> class="remember-check"/>
          ارسال ایمیل هنگام ورود جدید
        </label>
        <button class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 rounded hover:from-purple-700 hover:to-indigo-700 text-sm">ذخیره</button>
      </form>
    </section>

    <section class="bg-gray-800 rounded-xl p-6 border border-gray-700">
      <h2 class="text-xl font-bold mb-4">نشست‌های اخیر</h2>
      <div class="text-sm text-gray-400 mb-3">در صورت مشاهده فعالیت مشکوک، رمز عبور خود را تغییر دهید.</div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-right divide-y divide-gray-700">
          <thead class="bg-gray-900/70">
            <tr class="text-purple-300 text-sm"><th class="py-3 px-2 font-semibold">شناسه</th><th class="py-3 px-2 font-semibold">IP</th><th class="py-3 px-2 font-semibold">زمان</th></tr>
          </thead>
          <tbody class="text-gray-300 text-sm">
            <?php if(!empty($sessionsRows)): foreach($sessionsRows as $s): ?>
              <tr class="hover:bg-gray-800/60"><td class="py-3 px-2">#<?php echo (int)$s['id']; ?></td><td class="py-3 px-2"><?php echo htmlspecialchars($s['ip']??'-'); ?></td><td class="py-3 px-2"><?php echo htmlspecialchars($s['created_at']??'-'); ?></td></tr>
            <?php endforeach; else: ?>
              <tr><td colspan="3" class="py-6 text-center text-gray-500">داده‌ای برای نمایش وجود ندارد.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <form method="POST" class="mt-4 flex justify-end">
        <input type="hidden" name="action" value="clear_sessions"/>
        <button class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 text-sm">پاک‌سازی نشست‌ها</button>
      </form>
    </section>
  </main>
</body>
</html>


