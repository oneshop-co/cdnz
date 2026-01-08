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

$stmt=$pdo->prepare('SELECT r.id, r.name, r.version, ur.file_path, ur.id AS ur_id, ur.added_at FROM user_resources ur JOIN resources r ON r.id = ur.resource_id WHERE ur.user_id = ? ORDER BY ur.added_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$libs=$stmt->fetchAll(PDO::FETCH_ASSOC);

function formatBytesLocal($b){$u=['B','KB','MB','GB','TB'];if($b<=0)return [0,'B'];$p=min(floor(log($b,1024)),count($u)-1);return [round($b/pow(1024,$p),($p?2:0)),$u[$p]];}

// traffic aggregation for each file
$since=date('Y-m-d H:i:s',strtotime('-30 days'));
$map=[]; $q=$pdo->prepare('SELECT file_path, SUM(bytes) s FROM user_bandwidth WHERE user_id=? AND served_at>=? GROUP BY file_path');
$q->execute([$_SESSION['user_id'],$since]);
foreach($q as $r){$map[$r['file_path']]=(int)$r['s'];}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>کتابخانه‌های من | CDNz</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
  <style>html,body,.font-sans{font-family:'IRANSans','IranSans','IRANSansWeb',sans-serif!important}</style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
  <nav class="w-full bg-gray-900/80 backdrop-blur-md border-b border-gray-800 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-6 md:px-10 h-14 flex items-center justify-between">
      <a href="index.php#home" class="font-black">CDNz</a>
      <span class="font-black">کتابخانه‌های من</span>
      <a href="api/auth.php?action=logout" class="px-3 py-1.5 bg-purple-600 rounded hover:bg-purple-700 text-sm">خروج</a>
    </div>
  </nav>

  <main class="max-w-7xl mx-auto px-6 md:px-10 py-8">
    <div class="flex items-center justify-between mb-4 gap-3">
      <h1 class="text-2xl md:text-3xl font-extrabold">کتابخانه‌های من</h1>
      <div class="flex items-center gap-2">
        <a href="dashboard.php" class="px-4 py-2 bg-gray-700 rounded-lg hover:bg-gray-600 shadow text-sm">← داشبورد</a>
        <a href="dashboard.php#openAddCdn" onclick="window.location='dashboard.php'; return false;" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-lg hover:from-purple-700 hover:to-indigo-700 shadow text-sm">افزودن کتابخانه</a>
      </div>
    </div>

    <div class="bg-gray-800 rounded-xl p-5 md:p-6 border border-gray-700 shadow-lg overflow-x-auto">
      <table class="min-w-full text-right divide-y divide-gray-700">
        <thead class="bg-gray-900/70">
          <tr class="text-purple-300 text-sm">
            <th class="py-3 px-2 font-semibold">نام کتابخانه</th>
            <th class="py-3 px-2 font-semibold">نام فایل</th>
            <th class="py-3 px-2 font-semibold hidden sm:table-cell">نسخه</th>
            <th class="py-3 px-2 font-semibold hidden md:table-cell">تاریخ افزودن</th>
            <th class="py-3 px-2 font-semibold hidden lg:table-cell">ترافیک ۳۰ روز</th>
            <th class="py-3 px-2 font-semibold">عملیات</th>
          </tr>
        </thead>
        <tbody class="text-gray-300 text-sm">
        <?php foreach($libs as $lib): ?>
          <tr class="hover:bg-gray-800/60">
            <td class="py-3 px-2 font-medium"><?php echo htmlspecialchars($lib['name']); ?></td>
            <td class="py-3 px-2 text-xs text-gray-400"><?php echo htmlspecialchars(basename($lib['file_path'])); ?></td>
            <td class="py-3 px-2 hidden sm:table-cell"><span class="inline-block px-2 py-0.5 bg-purple-700/50 rounded text-purple-200 text-xs"><?php echo htmlspecialchars($lib['version']??'—'); ?></span></td>
            <td class="px-2 text-xs text-gray-400 hidden md:table-cell"><?php echo date('Y/m/d', strtotime($lib['added_at'])); ?></td>
            <?php $bytes=$map[$lib['file_path']]??0; [$v,$u]=formatBytesLocal($bytes); ?>
            <td class="px-2 text-xs text-gray-300 hidden lg:table-cell"><?php echo "$v $u"; ?></td>
            <td class="px-2 py-3 flex items-center gap-2">
              <?php $url='https://cdnz.ir/'.$lib['file_path']; ?>
              <button class="copy-link px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-green-300 text-xs" data-url="<?php echo htmlspecialchars($url); ?>">کپی لینک</button>
              <form action="api/user_library.php" method="POST" onsubmit="return confirm('حذف شود؟');">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?php echo (int)$lib['ur_id']; ?>"/>
                <button class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-red-300 text-xs">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; if(empty($libs)): ?>
          <tr><td colspan="6" class="py-6 text-center text-gray-500">هنوز کتابخانه‌ای اضافه نشده است.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <script>
    document.querySelectorAll('.copy-link').forEach(btn=>{
      btn.addEventListener('click',async()=>{
        const url=btn.getAttribute('data-url');
        try{
          if(navigator.clipboard && window.isSecureContext){await navigator.clipboard.writeText(url);} else {
            const ta=document.createElement('textarea'); ta.value=url; ta.style.position='fixed'; ta.style.opacity='0'; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
          }
          btn.textContent='کپی شد'; setTimeout(()=>btn.textContent='کپی لینک',1200);
        }catch(e){ alert('کپی نشد'); }
      });
    });
  </script>
</body>
</html>


