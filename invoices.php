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

// Ensure invoices table (lightweight)
$pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_id INT NOT NULL,
  amount INT NOT NULL,
  status ENUM('paid','unpaid','cancelled') DEFAULT 'paid',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4");

// Sample generator for demo if user has no invoices (optional)
$seed=$pdo->prepare('SELECT COUNT(*) FROM invoices WHERE user_id=?');
$seed->execute([$_SESSION['user_id']]);
if(!$seed->fetchColumn()){
  $pdo->prepare('INSERT INTO invoices(user_id,plan_id,amount,status) VALUES(?,?,?,?)')->execute([$_SESSION['user_id'],2,500000,'paid']);
}

// Fetch
$stmt=$pdo->prepare('SELECT i.id, i.plan_id, i.amount, i.status, i.created_at, p.name AS plan_name FROM invoices i LEFT JOIN plans p ON p.id=i.plan_id WHERE i.user_id=? ORDER BY i.created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>صورت‌حساب‌ها | CDNz</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
  <style>html,body,.font-sans{font-family:'IRANSans','IranSans','IRANSansWeb',sans-serif!important}</style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
  <nav class="w-full bg-gray-900/80 backdrop-blur-md border-b border-gray-800 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-6 md:px-10 h-14 flex items-center justify-between">
      <a href="index.php#home" class="font-black">CDNz</a>
      <span class="font-black">صورت‌حساب‌ها</span>
      <a href="api/auth.php?action=logout" class="px-3 py-1.5 bg-purple-600 rounded hover:bg-purple-700 text-sm">خروج</a>
    </div>
  </nav>

  <main class="max-w-7xl mx-auto px-6 md:px-10 py-8">
    <div class="flex items-center justify-between mb-4 gap-3">
      <h1 class="text-2xl md:text-3xl font-extrabold">صورت‌حساب‌ها</h1>
      <div class="flex items-center gap-2 text-sm text-gray-400">
        <span>برای پرداخت پلن‌ها به بخش ارتقاء پلن در داشبورد بروید</span>
        <a href="dashboard.php" class="px-3 py-1.5 bg-gray-700 rounded hover:bg-gray-600">بازگشت به داشبورد</a>
      </div>
    </div>

    <div class="bg-gray-800 rounded-xl p-5 md:p-6 border border-gray-700 shadow-lg overflow-x-auto">
      <table class="min-w-full text-right divide-y divide-gray-700">
        <thead class="bg-gray-900/70">
          <tr class="text-purple-300 text-sm">
            <th class="py-3 px-2 font-semibold">شماره</th>
            <th class="py-3 px-2 font-semibold">پلن</th>
            <th class="py-3 px-2 font-semibold">مبلغ (ریال)</th>
            <th class="py-3 px-2 font-semibold">وضعیت</th>
            <th class="py-3 px-2 font-semibold">تاریخ</th>
            <th class="py-3 px-2 font-semibold">رسید</th>
          </tr>
        </thead>
        <tbody class="text-gray-300 text-sm">
          <?php foreach($rows as $r): ?>
            <tr class="hover:bg-gray-800/60">
              <td class="py-3 px-2">#<?php echo (int)$r['id']; ?></td>
              <td class="py-3 px-2"><?php echo htmlspecialchars($r['plan_name']??('پلن '.$r['plan_id'])); ?></td>
              <td class="py-3 px-2"><?php echo number_format((int)$r['amount']); ?></td>
              <td class="py-3 px-2">
                <?php if($r['status']==='paid'): ?>
                  <span class="px-2 py-0.5 rounded bg-green-700/40 text-green-300">پرداخت‌شده</span>
                <?php elseif($r['status']==='unpaid'): ?>
                  <span class="px-2 py-0.5 rounded bg-yellow-700/40 text-yellow-300">پرداخت‌نشده</span>
                <?php else: ?>
                  <span class="px-2 py-0.5 rounded bg-gray-700/40 text-gray-300">لغوشده</span>
                <?php endif; ?>
              </td>
              <td class="py-3 px-2"><?php echo date('Y/m/d', strtotime($r['created_at'])); ?></td>
              <td class="py-3 px-2"><a href="#" class="underline text-purple-300 hover:text-purple-200">دانلود PDF</a></td>
            </tr>
          <?php endforeach; if(empty($rows)): ?>
            <tr><td colspan="6" class="py-6 text-center text-gray-500">صورت‌حسابی ثبت نشده است.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>


