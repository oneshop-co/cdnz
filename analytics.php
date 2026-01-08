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

// Window: last 30 days
$userId=(int)$_SESSION['user_id'];
$since=date('Y-m-d 00:00:00', strtotime('-29 days')); // include today
$labels=[];$series=[];
for($i=29;$i>=0;$i--){$labels[]=date('Y-m-d',strtotime("-$i days"));$series[]=0;}
$indexByDate=array_flip($labels);

$stmt=$pdo->prepare("SELECT DATE(served_at) d, SUM(bytes) s FROM user_bandwidth WHERE user_id=? AND served_at>=? GROUP BY d ORDER BY d");
$stmt->execute([$userId,$since]);
$totalBytes=0;
foreach($stmt as $row){
  $d=$row['d'];$s=(int)$row['s'];$totalBytes+=$s;
  if(isset($indexByDate[$d])){ $series[$indexByDate[$d]]=$s; }
}

// Top files
$stmtTop=$pdo->prepare("SELECT file_path, SUM(bytes) s FROM user_bandwidth WHERE user_id=? AND served_at>=? GROUP BY file_path ORDER BY s DESC LIMIT 8");
$stmtTop->execute([$userId,$since]);
$topFiles=[]; foreach($stmtTop as $r){$topFiles[]=['file'=>$r['file_path'],'bytes'=>(int)$r['s']];}

function formatBytesLocal($b){$u=['B','KB','MB','GB','TB'];if($b<=0)return [0,'B'];$pow=min(floor(log($b,1024)),count($u)-1);return [round($b/pow(1024,$pow),($pow?2:0)),$u[$pow]];}
[$tv,$tu]=formatBytesLocal($totalBytes);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تحلیل مصرف | CDNz</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    html,body,.font-sans{font-family:'IRANSans','IranSans','IRANSansWeb',sans-serif!important}
  </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
  <nav class="w-full bg-gray-900/80 backdrop-blur-md border-b border-gray-800 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-6 md:px-10 h-14 flex items-center justify-between">
      <a href="index.php#home" class="font-black">CDNz</a>
      <span class="font-black">تحلیل مصرف</span>
      <a href="api/auth.php?action=logout" class="px-3 py-1.5 bg-purple-600 rounded hover:bg-purple-700 text-sm">خروج</a>
    </div>
  </nav>

  <main class="max-w-7xl mx-auto px-6 md:px-10 py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl md:text-3xl font-extrabold">مرور ۳۰ روز اخیر</h1>
      <a href="dashboard.php" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-lg hover:from-purple-700 hover:to-indigo-700 shadow text-sm">بازگشت به داشبورد</a>
    </div>

    <section class="grid md:grid-cols-3 gap-6 mb-8">
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <p class="text-gray-400 text-sm mb-1">مصرف کل</p>
        <p class="text-4xl font-extrabold text-purple-400"><?="$tv $tu"?></p>
      </div>
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <p class="text-gray-400 text-sm mb-1">میانگین روزانه</p>
        <p class="text-3xl font-extrabold text-indigo-400"><?php $avg=$totalBytes/30;[$av,$au]=formatBytesLocal($avg); echo "$av $au"; ?></p>
      </div>
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <p class="text-gray-400 text-sm mb-1">پر مصرف‌ترین فایل</p>
        <p class="text-sm break-all"><?php echo isset($topFiles[0])?htmlspecialchars($topFiles[0]['file']):'—'; ?></p>
      </div>
    </section>

    <section class="grid md:grid-cols-3 gap-6">
      <div class="md:col-span-2 bg-gray-800 rounded-xl p-6 border border-gray-700 overflow-hidden">
        <h2 class="text-xl font-bold mb-4">نمودار روزانه</h2>
        <div class="relative h-48 sm:h-64 md:h-72">
          <canvas id="dailyChart" class="absolute inset-0 w-full h-full"></canvas>
        </div>
      </div>
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 max-h-64 sm:max-h-72 overflow-y-auto">
        <h2 class="text-xl font-bold mb-4">فایل‌های پرترافیک</h2>
        <ul class="space-y-2 text-sm">
          <?php foreach($topFiles as $t): [$v,$u]=formatBytesLocal($t['bytes']); ?>
            <li class="flex justify-between gap-3"><span class="truncate" title="<?=htmlspecialchars($t['file'])?>"><?=htmlspecialchars(basename($t['file']))?></span><span class="text-gray-400"><?="$v $u"?></span></li>
          <?php endforeach; if(empty($topFiles)) echo '<li class="text-gray-500">داده‌ای نیست</li>'; ?>
        </ul>
      </div>
    </section>
  </main>

  <script>
    const labels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
    const data = <?php echo json_encode($series); ?>;
    const ctx = document.getElementById('dailyChart');
    new Chart(ctx, {
      type: 'line',
      data: { labels, datasets: [{ label: 'Bytes', data, tension:.35, fill:true, borderColor:'#a78bfa', backgroundColor:'rgba(167,139,250,.15)', pointRadius:2 }] },
      options: { maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:'#9ca3af' } }, y:{ ticks:{ color:'#9ca3af', callback:v=>{ const units=['B','KB','MB','GB','TB']; let i=0; while(v>=1024&&i<units.length-1){v/=1024;i++;} return (i? v.toFixed(1):v)+' '+units[i]; } } } } }
    });
  </script>
</body>
</html>


