<?php
session_start();
// اگر کاربر لاگین نیست، به صفحه اصلی هدایت شود
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/api/db.php';
// اطمینان از وجود پلن‌های به‌روز
$seedPlans=[[1,'Free',0,1073741824],[2,'Standard',500000,10737418240],[3,'Pro',700000,16106127360],[4,'Business',1200000,32212254720]];
$pdo->exec("CREATE TABLE IF NOT EXISTS plans(id INT PRIMARY KEY,name VARCHAR(50),price INT,limit_bytes BIGINT UNSIGNED)");
foreach($seedPlans as $p){[$id,$name,$price,$bytes]=$p;$stmt=$pdo->prepare("REPLACE INTO plans(id,name,price,limit_bytes) VALUES(?,?,?,?)");$stmt->execute([$id,$name,$price,$bytes]);}

// اطمینان از وجود جدول user_bandwidth و ستون file_path
$pdo->exec("CREATE TABLE IF NOT EXISTS user_bandwidth (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  bytes BIGINT UNSIGNED NOT NULL,
  served_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_file (user_id, file_path)
) ENGINE=InnoDB CHARSET=utf8mb4");

$ubCol=$pdo->query("SHOW COLUMNS FROM user_bandwidth LIKE 'file_path'");
if($ubCol && $ubCol->rowCount()==0){
   $pdo->exec("ALTER TABLE user_bandwidth ADD COLUMN file_path VARCHAR(255) NOT NULL AFTER user_id");
   try{ $pdo->exec("CREATE INDEX idx_user_file ON user_bandwidth(user_id, file_path)"); }catch(PDOException $e){}
}

// ایجاد جدول ارتباطی کاربر ⇆ منابع در صورت عدم وجود
$pdo->exec("CREATE TABLE IF NOT EXISTS user_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    resource_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_file (user_id, file_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// اطمینان از وجود ستون file_path در جدول قدیمی
$chkFp = $pdo->query("SHOW COLUMNS FROM user_resources LIKE 'file_path'");
if($chkFp && $chkFp->rowCount()==0){
    $pdo->exec("ALTER TABLE user_resources ADD COLUMN file_path VARCHAR(255) NOT NULL DEFAULT ''");
}

// اطمینان از وجود ستون link_token در جدول users و تولید توکن برای کاربر
$chkTok=$pdo->query("SHOW COLUMNS FROM users LIKE 'link_token'");
if($chkTok && $chkTok->rowCount()==0){
    $pdo->exec("ALTER TABLE users ADD COLUMN link_token VARCHAR(64) UNIQUE NULL");
}

// دریافت توکن کاربر (و ساخت در صورت خالی بودن)
$stmtTok=$pdo->prepare('SELECT link_token FROM users WHERE id=?');
$stmtTok->execute([$_SESSION['user_id']]);
$token=$stmtTok->fetchColumn();
if(!$token){
    $token=bin2hex(random_bytes(16));
    $upd=$pdo->prepare('UPDATE users SET link_token=? WHERE id=?');
    $upd->execute([$token,$_SESSION['user_id']]);
}

// دریافت تمام منابع برای جستجو در مودال همراه فایل‌ها
// ensure file_labels column exists for older databases
try{ $pdo->query("SELECT file_labels FROM resources LIMIT 1"); }
catch(PDOException $e){ $pdo->exec("ALTER TABLE resources ADD COLUMN file_labels TEXT NULL"); }
$rows = $pdo->query('SELECT r.id, r.name, r.logo, r.version, r.local_path, r.file_labels, c.title AS category FROM resources r LEFT JOIN resource_categories c ON c.id = r.category_id ORDER BY c.title, r.name')->fetchAll(PDO::FETCH_ASSOC);
$allResources = [];
foreach($rows as $r){
    $folder = __DIR__.'/'.$r['local_path']; // absolute path
    $files = [];
    if(is_dir($folder)){
        foreach(scandir($folder) as $f){
            if($f==='.'||$f==='..') continue;
            $files[] = $r['local_path'].'/'.$f; // relative path like cdn/bootstrap/xyz.js
        }
    }
    $r['files'] = $files;
    // decode labels map (base filename => label)
    $labels = [];
    if(!empty($r['file_labels'])){
        $decoded = json_decode($r['file_labels'], true);
        if(is_array($decoded)) $labels = $decoded;
    }
    $r['labels'] = $labels;
    $allResources[] = $r;
}

// دریافت کتابخانه‌های انتخاب‌شده توسط کاربر
$stmtLib = $pdo->prepare('SELECT r.id, r.name, r.version, ur.file_path, ur.id AS ur_id, ur.added_at FROM user_resources ur JOIN resources r ON r.id = ur.resource_id WHERE ur.user_id = ? ORDER BY ur.added_at DESC');
$stmtLib->execute([$_SESSION['user_id']]);
$userLibs = $stmtLib->fetchAll(PDO::FETCH_ASSOC);

// -------------- محاسبه مصرف پهنای باند ----------------
$now = date('Y-m-d H:i:s');
$subStmt = $pdo->prepare("SELECT p.id as plan_id, p.limit_bytes, s.expires_at
                          FROM user_subscriptions s
                          JOIN plans p ON p.id = s.plan_id
                          WHERE s.user_id=? AND s.expires_at>? ORDER BY s.expires_at DESC LIMIT 1");
$subStmt->execute([$_SESSION['user_id'],$now]);
$subRow = $subStmt->fetch();
$limit = $subRow ? $subRow['limit_bytes'] : 1073741824; // 1 GiB
$since = date('Y-m-d H:i:s', strtotime('-30 days'));

$usedStmt = $pdo->prepare('SELECT COALESCE(SUM(bytes),0) FROM user_bandwidth WHERE user_id=? AND served_at>=?');
$usedStmt->execute([$_SESSION['user_id'],$since]);
$used = (int)$usedStmt->fetchColumn();

$percent = $limit ? min(100, ($used / $limit) * 100) : 0;

function formatBytesLocal($b){
    $u=['B','KB','MB','GB','TB'];
    if($b<=0) return [0,'B'];
    $pow=min(floor(log($b,1024)),count($u)-1);
    return [round($b/pow(1024,$pow), ($pow?2:0)),$u[$pow]];
}
[$usedVal,$usedUnit]  = formatBytesLocal($used);
[$limitVal,$limitUnit]= formatBytesLocal($limit);
$trafficStmt=$pdo->prepare('SELECT file_path, SUM(bytes) AS total FROM user_bandwidth WHERE user_id=? AND served_at>=? GROUP BY file_path');
$trafficStmt->execute([$_SESSION['user_id'],$since]);
$trafficMap=[];
foreach($trafficStmt as $tr){
   $trafficMap[$tr['file_path']] = (int)$tr['total'];
}

$planLabel='رایگان';
if($subRow){
    switch($subRow['plan_id']){
        case 2:$planLabel='استاندارد';break;
        case 3:$planLabel='پرو';break;
        case 4:$planLabel='بیزینس';break;
    }
}
// ------------------------------------------------------

// محاسبه وضعیت و تعداد روزهای باقی‌مانده اشتراک برای نمایش در کارت اطلاعات حساب
$expireAt = $subRow['expires_at'] ?? null;
$expireFmt = null; $daysLeft = null; $expClass = 'exp-free'; $expTitle = 'پلن رایگان';
if($expireAt){
    $expTs = strtotime($expireAt);
    $expireFmt = date('Y/m/d', $expTs);
    $daysLeft = (int)ceil(($expTs - time())/86400);
    if($expTs <= time()){
        $expClass = 'exp-expired';
        $expTitle = 'اشتراک شما به پایان رسیده';
        $daysLeft = 0;
    }elseif($daysLeft <= 2){
        $expClass = 'exp-urgent';
        $expTitle = 'فقط '.$daysLeft.' روز تا پایان اشتراک';
    }elseif($daysLeft <= 5){
        $expClass = 'exp-warn';
        $expTitle = $daysLeft.' روز تا پایان اشتراک';
    }else{
        $expClass = 'exp-good';
        $expTitle = $daysLeft.' روز تا پایان اشتراک';
    }
}

// بررسی دسترسی کاربر
$freeUsed = $pdo->prepare('SELECT id FROM free_plan_used WHERE user_id = ?');
$freeUsed->execute([$_SESSION['user_id']]);

if ($freeUsed->fetch()) {
    // کاربر قبلاً از پلن رایگان استفاده کرده، بررسی اشتراک فعال
    if (!$subRow || $subRow['expires_at'] <= $now) {
        // اشتراک منقضی شده، هدایت به صفحه پرداخت
        header('Location: subscription_expired.php?user_id=' . $_SESSION['user_id']);
        exit;
    }
}

// دریافت اطلاعات کاربر
$stmt = $pdo->prepare('SELECT name, email, created_at FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) {
    // در صورت عدم یافتن کاربر، خروج از سیستم
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد کاربر | CDNz</title>
    <meta name="description" content="حساب کاربری شما در CDNz - مدیریت کتابخانه‌ها و پهنای باند.">
    <link rel="canonical" href="https://cdnz.ir/dashboard">
    <!-- Site Icon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/uploads/icons/cdnz.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/uploads/icons/cdnz.png">
    <meta name="robots" content="noindex,follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="داشبورد CDNz">
    <meta property="og:description" content="مدیریت کتابخانه‌های اضافه‌شده و دریافت لینک CDN اختصاصی.">
    <meta property="og:url" content="https://cdnz.ir/dashboard">
    <meta property="og:image" content="https://cdnz.ir/assets/og-dashboard.png">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="داشبورد | CDNz">
    <meta name="twitter:description" content="کتابخانه‌های من در CDNz.">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- IranSans همان‌طور که قبلاً اضافه شده است -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
    <style>
        html, body, .font-sans {
            font-family: 'IRANSans', 'IranSans', 'IRANSansWeb', sans-serif !important;
        }
        .dash-nav{background:linear-gradient(180deg, rgba(15,23,42,.92), rgba(15,23,42,.72));backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border-bottom:1px solid rgba(148,163,184,.14)}
        .brand-chip{display:inline-flex;align-items:center;gap:.5rem;padding:.4rem .65rem;border-radius:9999px;border:1px solid rgba(167,139,250,.35);background:rgba(99,102,241,.12);color:#fff}
        .dash-link{position:relative;padding-bottom:4px;color:#cbd5e1;transition:color .2s ease}
        .dash-link:hover{color:#fff}
        .dash-link::after{content:'';position:absolute;bottom:0;left:0;width:100%;height:2px;background:linear-gradient(90deg,#a78bfa,#06b6d4);transform:scaleX(0);transform-origin:right;transition:transform .25s ease}
        html[dir='rtl'] .dash-link::after{transform-origin:left}
        .dash-link:hover::after,.dash-link.active::after{transform:scaleX(1)}
        .dash-aside{
            background:linear-gradient(180deg,#5b42d6 0%, #4b32c0 100%);
            border:1px solid rgba(255,255,255,.08);
        }
        .dash-item{display:flex;align-items:center;gap:.6rem;padding:.65rem .9rem;border-radius:12px;color:#eef2ff;transition:background .2s ease,color .2s ease}
        .dash-item i{opacity:.9}
        .dash-item:hover{background:rgba(255,255,255,.10);color:#fff}
        .dash-active{background:linear-gradient(135deg,#a78bfa 0%, #f97316 100%);color:#fff}
        .dash-label{font-size:.9rem;color:#e0e7ff}
        /* Subscription status badge */
        .exp-badge{display:flex;align-items:center;gap:.6rem;padding:12px 14px;border-radius:14px;border:1px solid rgba(255,255,255,.08);box-shadow:0 10px 24px rgba(0,0,0,.25);font-weight:800}
        .exp-good{background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(59,130,246,.12));color:#bbf7d0;border-color:rgba(16,185,129,.35);animation:breathing 3s ease-in-out infinite}
        .exp-warn{background:linear-gradient(135deg,rgba(245,158,11,.18),rgba(99,102,241,.14));color:#fde68a;border-color:rgba(245,158,11,.4);animation:pulseGlow 1.4s ease-in-out infinite}
        .exp-urgent{background:linear-gradient(135deg,rgba(239,68,68,.22),rgba(147,51,234,.18));color:#fecaca;border-color:rgba(239,68,68,.5);animation:wiggle 1.8s ease-in-out infinite}
        .exp-expired{background:linear-gradient(135deg,rgba(239,68,68,.28),rgba(31,41,55,.6));color:#fecaca;border-color:rgba(239,68,68,.55);animation:blink 1.2s steps(2) infinite}
        .exp-free{background:linear-gradient(135deg,rgba(99,102,241,.18),rgba(6,182,212,.14));color:#e9d5ff;border-color:rgba(99,102,241,.35)}
        .exp-days{font-size:1.25rem;letter-spacing:.5px}
        .exp-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.15rem .5rem;border-radius:9999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);font-size:.72rem}
        @keyframes pulseGlow{0%{box-shadow:0 0 0 0 rgba(245,158,11,.35)}70%{box-shadow:0 0 0 12px rgba(245,158,11,0)}100%{box-shadow:0 0 0 0 rgba(245,158,11,0)}}
        @keyframes wiggle{0%,100%{transform:translateX(0)}15%{transform:translateX(-2px)}30%{transform:translateX(2px)}45%{transform:translateX(-1px)}60%{transform:translateX(1px)}}
        @keyframes breathing{0%{transform:scale(1)}50%{transform:scale(1.02)}100%{transform:scale(1)}}
        @keyframes blink{50%{opacity:.5}}
    </style>
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-900 text-white font-sans scroll-smooth min-h-screen">
    <!-- Navigation (حرفه‌ای‌تر) -->
    <nav class="dash-nav w-full fixed top-0 z-20">
        <div class="max-w-7xl mx-auto px-4 md:px-10 h-14 flex items-center justify-between">
            <a href="index.php#home" class="brand-chip text-sm md:text-base">
                <i data-feather="zap" class="w-4 h-4"></i>
                <span class="font-black tracking-tight">CDNz</span>
            </a>
            <div class="flex items-center gap-5 md:gap-6 text-gray-300">
                <a href="index.php#features" class="dash-link hidden md:inline">ویژگی‌ها</a>
                <a href="index.php#cdns" class="dash-link hidden md:inline">کتابخانه‌ها</a>
                <a href="index.php#pricing" class="dash-link hidden md:inline">تعرفه‌ها</a>
                <a href="dashboard.php" class="dash-link active hidden md:inline">داشبورد</a>
                <a href="api/auth.php?action=logout" class="px-4 py-2 rounded-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 shadow">خروج</a>
                <button id="dashMenuBtn" class="md:hidden ml-2 focus:outline-none"><i data-feather="menu"></i></button>
            </div>
        </div>
    </nav>

    <div id="dashOverlay" class="fixed inset-0 bg-black/50 opacity-0 pointer-events-none md:hidden transition-opacity z-20"></div>

    <div id="dashLayout" class="pt-20 md:pt-24 max-w-7xl mx-auto grid md:grid-cols-12 gap-6 px-4 md:px-8 items-start">
    <aside id="dashAside" class="dash-aside fixed top-16 bottom-0 right-0 w-80 md:w-auto text-white md:static md:col-span-3 md:h-auto md:rounded-2xl rounded-l-2xl rtl:rounded-r-2xl transform translate-x-full rtl:-translate-x-full md:transform-none md:translate-x-0 transition-transform z-30 overflow-hidden">
        <div class="h-full md:h-auto overflow-y-auto p-6 space-y-3">
            <p class="dash-label mb-2">منوی اصلی</p>
            <a href="dashboard.php" class="dash-item dash-active"><i data-feather="home" class="w-5 h-5"></i><span>داشبورد</span></a>
            <a href="analytics.php" class="dash-item"><i data-feather="bar-chart-2" class="w-5 h-5"></i><span>تحلیل مصرف</span></a>
            <a href="libraries.php" class="dash-item"><i data-feather="book-open" class="w-5 h-5"></i><span>کتابخانه‌های من</span></a>
            <a href="invoices.php" class="dash-item"><i data-feather="credit-card" class="w-5 h-5"></i><span>صورت‌حساب‌ها</span></a>
            <a href="account.php" class="dash-item"><i data-feather="user" class="w-5 h-5"></i><span>اطلاعات حساب</span></a>
            <a href="security.php" class="dash-item"><i data-feather="shield" class="w-5 h-5"></i><span>امنیت</span></a>
        </div>
    </aside>

    <main class="w-full md:col-span-9 px-1 sm:px-4 md:px-0 pt-6 md:pt-0">
        <?php if (isset($_GET['renewed']) && $_GET['renewed'] == '1'): ?>
        <div class="mb-6 p-4 bg-gradient-to-r from-green-600 to-emerald-600 rounded-xl text-white text-center">
            <div class="flex items-center justify-center gap-2 mb-2">
                <i data-feather="check-circle" class="w-6 h-6"></i>
                <span class="text-lg font-semibold">اشتراک شما با موفقیت تمدید شد!</span>
            </div>
            <p class="text-green-100">حالا می‌توانید از تمام امکانات سرویس استفاده کنید.</p>
        </div>
        <?php endif; ?>
        
        <h1 class="text-2xl md:text-3xl font-extrabold mb-6">سلام، <?php echo htmlspecialchars($user['name']); ?> 👋</h1>

        <section class="grid md:grid-cols-3 gap-4 md:gap-6">
            <!-- کارت اطلاعات حساب -->
            <div class="md:col-span-2 bg-gray-800 rounded-xl p-5 md:p-6 border border-gray-700 shadow-lg">
                <h2 class="text-2xl font-bold mb-4">اطلاعات حساب</h2>
                <ul class="space-y-2 text-gray-300">
                    <li><span class="text-gray-400">نام:</span> <?php echo htmlspecialchars($user['name']); ?></li>
                    <li><span class="text-gray-400">ایمیل:</span> <?php echo htmlspecialchars($user['email']); ?></li>
                    <li><span class="text-gray-400">عضویت از:</span> <?php echo date('Y/m/d', strtotime($user['created_at'])); ?></li>
                </ul>
                <div class="mt-5">
                    <div class="exp-badge <?=$expClass?>">
                        <i data-feather="clock" class="w-5 h-5"></i>
                        <div>
                          <div class="exp-days">
                            <?php if($expireFmt): ?>
                              <span><?=$daysLeft?></span> روز مانده
                            <?php else: ?>
                              <?=$expTitle?>
                            <?php endif; ?>
                          </div>
                          <div class="text-xs text-gray-300/80 mt-0.5">
                            <?php if($expireFmt): ?>
                              پایان: <?=$expireFmt?>
                            <?php else: ?>
                              پلن فعلی: <?=$planLabel?>
                            <?php endif; ?>
                            <span class="exp-chip ml-2 rtl:mr-2">پلن: <?=$planLabel?></span>
                          </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- کارت پهنای باند -->
            <div class="bg-gray-800 rounded-xl p-5 md:p-6 border border-gray-700 shadow-lg flex flex-col justify-between">
                <div>
                    <h2 class="text-2xl font-bold mb-4">مصرف پهنای باند</h2>
                    <p class="text-4xl font-extrabold text-purple-400"><?=$usedVal?>&thinsp;<?=$usedUnit?></p>
                    <p class="text-gray-400 text-sm">از <?=$limitVal?>&thinsp;<?=$limitUnit?> پلن <?=$planLabel?></p>
                    <div class="w-full bg-gray-700/50 rounded h-2 mt-4">
                        <div class="bg-purple-600 h-2 rounded" style="width:<?=$percent?>%"></div>
                    </div>
                    <?php if($percent>=100): ?>
                      <div class="mt-3 text-red-300 text-sm bg-red-800/30 border border-red-700 rounded p-2">سقف مصرف شما تکمیل شده است.</div>
                    <?php elseif($percent>=95): ?>
                      <div class="mt-3 text-amber-200 text-sm bg-amber-800/30 border border-amber-700 rounded p-2">هشدار: ۹۵٪ از سهمیه مصرف شده است.</div>
                    <?php elseif($percent>=80): ?>
                      <div class="mt-3 text-yellow-200 text-sm bg-yellow-800/30 border border-yellow-700 rounded p-2">هشدار: ۸۰٪ از سهمیه مصرف شده است.</div>
                    <?php endif; ?>
                </div>
                <button id="openUpgrade" class="mt-6 px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-lg hover:from-purple-700 hover:to-indigo-700">ارتقاء پلن</button>
            </div>
        </section>

        <!-- Suggestion Box -->
        <div class="mt-12 bg-gradient-to-r from-purple-800/40 to-indigo-800/30 border border-purple-700/50 ring-1 ring-purple-700/30 shadow-inner rounded-xl px-6 py-6 flex flex-col sm:flex-row items-center justify-between gap-4 backdrop-blur-sm">
            <div class="flex items-center gap-3">
                <i data-feather="alert-circle" class="w-6 h-6 text-purple-400 animate-pulse"></i>
                <p class="font-medium text-gray-200">چیزی که میخوای رو پیدا نکردی؟ بهمون خبر بده!</p>
            </div>
            <button id="notifyBtn" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-sm shadow-lg transition-colors duration-150">ارسال درخواست</button>
        </div>

        <!-- Request Modal -->
        <div id="reqModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center hidden z-50 px-4">
          <div class="bg-gray-800 rounded-xl p-8 w-full max-w-md shadow-2xl ring-1 ring-purple-500/30">
             <h3 class="text-2xl font-bold text-center text-purple-400 mb-6">ارسال درخواست کتابخانه</h3>
             <textarea id="reqText" class="w-full h-32 rounded bg-gray-700 p-3 text-gray-200 placeholder-gray-400 focus:ring-2 focus:ring-purple-600 focus:outline-none" placeholder="توضیحات..."></textarea>
             <div class="flex justify-end gap-4 mt-6">
                 <button id="reqCancel" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-500 text-sm">انصراف</button>
                 <button id="reqSend" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded text-sm">ارسال</button>
             </div>
          </div>
        </div>

        <!-- Token controls (moved above libraries) -->
        <section class="mt-8 md:mt-10 bg-gray-800 rounded-xl p-5 md:p-6 border border-gray-700 shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-2xl font-bold">لینک اختصاصی</h2>
                <div class="space-x-2 rtl:space-x-reverse">
                    <button id="tokRotate" class="px-3 py-1.5 bg-blue-600 rounded text-sm hover:bg-blue-700">چرخش توکن</button>
                    <button id="tokRevoke" class="px-3 py-1.5 bg-red-600 rounded text-sm hover:bg-red-700">لغو توکن</button>
                </div>
            </div>
            <div class="text-xs text-gray-300 break-all" id="tokValue">در حال بارگذاری...</div>
            <div class="mt-2 text-xs text-gray-400">نمونه لینک: <span class="break-all" id="tokSample"></span></div>
        </section>

        <!-- بخش کتابخانه‌های محبوب -->
        <section class="mt-8 md:mt-12 mb-16 bg-gray-800 rounded-xl p-5 md:p-6 border border-gray-700 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold">کتابخانه‌های من</h2>
                <button id="openAddCdn" class="px-4 py-2 bg-purple-600 rounded hover:bg-purple-700">افزودن کتابخانه</button>
            </div>

            <!-- لیست کارت در موبایل -->
            <div class="space-y-4 sm:hidden">
            <?php foreach($userLibs as $lib): ?>
                <div class="bg-gray-700/60 border border-gray-600 rounded-lg p-4 flex justify-between items-start shadow-md">
                    <div>
                        <p class="font-semibold text-purple-200 text-sm mb-1 line-clamp-1"><?=htmlspecialchars($lib['name'])?></p>
                        <p class="text-xs text-gray-400 mb-1 line-clamp-1"><?=htmlspecialchars(basename($lib['file_path']))?></p>
                        <p class="text-xs text-gray-500"><?=htmlspecialchars($lib['version']??'')?></p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <button class="get-link flex items-center gap-1 w-20 justify-center px-2 py-1 bg-gray-600 hover:bg-gray-500 rounded text-green-300 text-xs" data-path="<?=$lib['file_path']?>"><i data-feather="copy" class="w-4 h-4"></i><span>کپی</span></button>
                        <button class="del-lib flex items-center gap-1 w-20 justify-center px-2 py-1 bg-gray-600 hover:bg-gray-500 rounded text-red-300 text-xs" data-id="<?=$lib['ur_id']?>"><i data-feather="trash-2" class="w-4 h-4"></i><span>حذف</span></button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if(empty($userLibs)): ?>
                <p class="text-center text-gray-500 text-sm">هنوز کتابخانه‌ای اضافه نشده است.</p>
            <?php endif; ?>
            </div>

            <div class="overflow-x-auto hidden sm:block">
                <table class="min-w-full text-right divide-y divide-gray-700">
                    <thead class="bg-gray-900/70">
                        <tr class="text-purple-400 text-sm">
                            <th class="py-3 px-2 font-semibold">نام کتابخانه</th>
                            <th class="py-3 px-2 font-semibold">نام فایل</th>
                            <th class="py-3 px-2 font-semibold hidden sm:table-cell">نسخه</th>
                            <th class="py-3 px-2 font-semibold hidden md:table-cell">تاریخ افزودن</th>
                            <th class="py-3 px-2 font-semibold hidden lg:table-cell">ترافیک مصرفی</th>
                            <th class="py-3 px-2 font-semibold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300 text-sm">
                    <?php foreach($userLibs as $lib): ?>
                        <tr class="hover:bg-gray-800/60">
                            <td class="py-3 px-2 font-medium"><?=htmlspecialchars($lib['name'])?></td>
                            <td class="py-3 px-2 text-xs text-gray-400"><?=htmlspecialchars(basename($lib['file_path']))?></td>
                            <td class="py-3 px-2 hidden sm:table-cell"><span class="inline-block px-2 py-0.5 bg-purple-700/50 rounded text-purple-200 text-xs"><?=htmlspecialchars($lib['version']??'—')?></span></td>
                            <td class="px-2 text-xs text-gray-400 hidden md:table-cell"><?=date('Y/m/d', strtotime($lib['added_at']))?></td>
                            <?php
                              $tbytes = $trafficMap[$lib['file_path']] ?? 0;
                              [$tv,$tu] = formatBytesLocal($tbytes);
                            ?>
                            <td class="px-2 text-xs text-gray-300 hidden lg:table-cell"><?=$tv?>&thinsp;<?=$tu?></td>
                            <td class="px-2 py-3 flex items-center gap-2 align-middle">
                                <button class="get-link flex items-center gap-1 w-20 justify-center px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-green-300 text-xs" data-path="<?=$lib['file_path']?>"><i data-feather="copy" class="w-4 h-4"></i><span>کپی لینک</span></button>
                                <button class="del-lib flex items-center gap-1 w-20 justify-center px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-red-300 text-xs" data-id="<?=$lib['ur_id']?>"><i data-feather="trash-2" class="w-4 h-4"></i><span>حذف</span></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($userLibs)): ?>
                        <tr><td colspan="6" class="py-4 text-center text-gray-500">هنوز کتابخانه‌ای اضافه نشده است.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
    </div><!-- /#dashLayout -->

    <script>
      (function(){
        const aside=document.getElementById('dashAside');
        const overlay=document.getElementById('dashOverlay');
        const btn=document.getElementById('dashMenuBtn');
        if(btn){
          btn.addEventListener('click',()=>{
            overlay.classList.toggle('opacity-100');
            overlay.classList.toggle('pointer-events-auto');
            aside.classList.toggle('translate-x-full');
            aside.classList.toggle('rtl:-translate-x-full');
          });
        }
        if(overlay){
          overlay.addEventListener('click',()=>{
            overlay.classList.remove('opacity-100','pointer-events-auto');
            aside.classList.add('translate-x-full');
            aside.classList.add('rtl:-translate-x-full');
          });
        }
      })();
    </script>

    <!-- مودال افزودن کتابخانه -->
    <div id="addCdnModal" class="fixed inset-0 bg-black/90 backdrop-blur-lg flex items-center justify-center hidden modal z-40 px-4">
        <div class="relative w-full max-w-lg bg-gray-800 rounded-2xl shadow-2xl ring-1 ring-purple-500/30 p-8">
            <button class="absolute top-4 left-4 text-gray-400 hover:text-gray-200 transition modal-close"><i data-feather="x"></i></button>
            <h3 class="text-2xl font-extrabold mb-6 text-center text-purple-400">افزودن کتابخانه</h3>
            <input type="text" id="cdnSearch" placeholder="جستجوی کتابخانه..." class="w-full mb-4 p-3 rounded-lg bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600 focus:outline-none">
            <div id="cdnResults" class="max-h-80 overflow-y-auto space-y-2"></div>
            <p id="cdnMsg" class="text-sm mt-4"></p>
        </div>
    </div>

    <!-- Upgrade Modal -->
    <div id="upgradeModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center hidden z-50 px-4">
      <div class="bg-gray-800 rounded-xl p-8 w-full max-w-md shadow-2xl ring-1 ring-purple-500/30">
         <h3 class="text-2xl font-bold text-center text-purple-400 mb-6">انتخاب پلن</h3>
         <div class="space-y-4">
            <div class="flex items-center justify-between bg-gray-700 rounded-lg p-4">
                <div>
                  <p class="font-semibold">پلن ۱۰ گیگابایت</p>
                  <span class="text-sm text-gray-400">۵۰۰٬۰۰۰ ریال / ماه</span>
                </div>
                <button onclick="location.href='pay.php?plan=2'" class="px-4 py-1 bg-purple-600 rounded hover:bg-purple-700 text-sm">انتخاب</button>
            </div>
            <div class="flex items-center justify-between bg-gray-700 rounded-lg p-4">
                <div>
                  <p class="font-semibold">پلن ۱۵ گیگابایت</p>
                  <span class="text-sm text-gray-400">۷۰۰٬۰۰۰ ریال / ماه</span>
                </div>
                <button onclick="location.href='pay.php?plan=3'" class="px-4 py-1 bg-purple-600 rounded hover:bg-purple-700 text-sm">انتخاب</button>
            </div>
            <div class="flex items-center justify-between bg-gray-700 rounded-lg p-4">
                <div>
                  <p class="font-semibold">پلن ۳۰ گیگابایت</p>
                  <span class="text-sm text-gray-400">۱٬۲۰۰٬۰۰۰ ریال / ماه</span>
                </div>
                <button onclick="location.href='pay.php?plan=4'" class="px-4 py-1 bg-purple-600 rounded hover:bg-purple-700 text-sm">انتخاب</button>
            </div>
         </div>
         <button id="closeUpgrade" class="mt-6 w-full px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded">بستن</button>
      </div>
    </div>

    <script id="resourcesData" type="application/json"><?=json_encode($allResources, JSON_UNESCAPED_UNICODE);?></script>
    <script id="userToken" type="application/json"><?=json_encode($token) ?></script>
    <script>
        feather.replace();

        // تابع نمایش پیام به صورت مودال
        const showAlert=(msg,type='info')=>{
            const cfg={
              success:['text-green-400','bg-green-600','check-circle'],
              error:['text-red-400','bg-red-600','alert-triangle'],
              info:['text-purple-400','bg-purple-600','info']
            }[type]||cfg.info;
            const [iconColor,btnColor,iconName]=cfg;

            const overlay=document.createElement('div');
            overlay.className='fixed inset-0 flex items-center justify-center z-50 opacity-0 transition-opacity duration-300 bg-black/70 backdrop-blur-sm';
            overlay.innerHTML=`<div class=\"alertBox transform scale-90 opacity-0 transition-all duration-300 bg-gray-800 rounded-xl p-8 w-full max-w-sm text-center shadow-2xl ring-1 ring-white/10\">\n                <div class=\"flex justify-center mb-4\"><i data-feather=\"${iconName}\" class=\"w-12 h-12 ${iconColor}\"></i></div>\n                <p class=\"text-gray-200 mb-6\">${msg}</p>\n                <button class=\"alertClose px-6 py-2 ${btnColor} rounded hover:brightness-110 text-sm\">باشه</button>\n            </div>`;
            document.body.appendChild(overlay);
            feather.replace();
            requestAnimationFrame(()=>{
               overlay.classList.add('opacity-100');
               const box=overlay.querySelector('.alertBox');
               box.classList.remove('scale-90','opacity-0');
               box.classList.add('scale-100','opacity-100');
            });
            const close=()=>{
               overlay.classList.remove('opacity-100');
               const box=overlay.querySelector('.alertBox');
               box.classList.add('scale-90','opacity-0');
               overlay.addEventListener('transitionend',()=>overlay.remove(),{once:true});
            };
            overlay.addEventListener('click',e=>{if(e.target===overlay) close();});
            overlay.querySelector('.alertClose').addEventListener('click',close);
        };

        // تابع تایید با مودال
        const showConfirm=(msg,type='question')=>{
            return new Promise(resolve=>{
                const cfg={
                  delete:['text-red-400','bg-red-600','trash-2'],
                  question:['text-yellow-400','bg-yellow-600','help-circle']
                }[type]||cfg.question;
                const [iconColor,btnColor,iconName]=cfg;
                const overlay=document.createElement('div');
                overlay.className='fixed inset-0 flex items-center justify-center z-50 opacity-0 transition-opacity duration-300 bg-black/70 backdrop-blur-sm';
                overlay.innerHTML=`<div class=\"confirmBox transform scale-90 opacity-0 transition-all duration-300 bg-gray-800 rounded-xl p-8 w-full max-w-sm text-center shadow-2xl ring-1 ring-white/10\">\n                    <div class=\"flex justify-center mb-4\"><i data-feather=\"${iconName}\" class=\"w-12 h-12 ${iconColor}\"></i></div>\n                    <p class=\"text-gray-200 mb-6\">${msg}</p>\n                    <div class=\"flex justify-center gap-4\">\n                        <button class=\"confirmYes px-6 py-2 ${btnColor} rounded hover:brightness-110 text-sm\">تایید</button>\n                        <button class=\"confirmNo px-6 py-2 bg-gray-600 rounded hover:bg-gray-500 text-sm\">انصراف</button>\n                    </div>\n                </div>`;
                document.body.appendChild(overlay);
                feather.replace();
                requestAnimationFrame(()=>{
                    overlay.classList.add('opacity-100');
                    const box=overlay.querySelector('.confirmBox');
                    box.classList.remove('scale-90','opacity-0');
                    box.classList.add('scale-100','opacity-100');
                });
                const close=(ans)=>{
                    overlay.classList.remove('opacity-100');
                    overlay.addEventListener('transitionend',()=>overlay.remove(),{once:true});
                    resolve(ans);
                };
                overlay.addEventListener('click',e=>{if(e.target===overlay) close(false);});
                overlay.querySelector('.confirmYes').addEventListener('click',()=>close(true));
                overlay.querySelector('.confirmNo').addEventListener('click',()=>close(false));
            });
        };

        // Modal handling
        const addCdnModal = document.getElementById('addCdnModal');
        const openAddCdn = document.getElementById('openAddCdn');
        const modalCloseBtns = document.querySelectorAll('.modal-close');
        openAddCdn?.addEventListener('click', () => addCdnModal.classList.remove('hidden'));
        modalCloseBtns.forEach(btn => btn.addEventListener('click', () => addCdnModal.classList.add('hidden')));

        // Upgrade modal
        const upM=document.getElementById('upgradeModal');
        document.getElementById('openUpgrade').addEventListener('click',()=>upM.classList.remove('hidden'));
        document.getElementById('closeUpgrade').addEventListener('click',()=>upM.classList.add('hidden'));

        // notify button
        const nb=document.getElementById('notifyBtn');
        nb?.addEventListener('click',()=>reqM.classList.remove('hidden'));

        // request modal logic
        const reqM=document.getElementById('reqModal');
        nb?.addEventListener('click',()=>reqM.classList.remove('hidden'));
        document.getElementById('reqCancel').addEventListener('click',()=>reqM.classList.add('hidden'));
        document.getElementById('reqSend').addEventListener('click',async()=>{
           const txt=document.getElementById('reqText').value.trim();
           if(!txt){showAlert('لطفاً توضیحات را وارد کنید','error');return;}
           const fd=new FormData();fd.append('action','add');fd.append('message',txt);
           const r=await fetch('api/request.php',{method:'POST',body:fd});
           const d=await r.json();
           if(d.success){showAlert('درخواست ثبت شد','success');reqM.classList.add('hidden');document.getElementById('reqText').value='';}
           else showAlert(d.message||'خطا','error');
        });

        // Resources data
        const resources = JSON.parse(document.getElementById('resourcesData').textContent);
        const cdnResults = document.getElementById('cdnResults');
        const cdnSearch  = document.getElementById('cdnSearch');
        const cdnMsg     = document.getElementById('cdnMsg');

        // گروه‌بندی منابع بر اساس دسته
        const groupByCat = () => {
           const map={};
           resources.forEach(r=>{
              const cat=r.category||'بدون دسته';
              if(!map[cat]) map[cat]=[];
              map[cat].push(r);
           });
           return Object.entries(map).map(([title,items])=>({title,items}));
        };

        let catGroups = groupByCat();

        const renderCategories = (cats) => {
           cdnResults.innerHTML='';
           cats.forEach(c=>{
              const row=document.createElement('div');
              row.className='flex justify-between items-center bg-gray-700 p-3 rounded mb-2';
              row.innerHTML=`<span class="text-sm font-semibold text-purple-200">${c.title}</span><button class="show-cat px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-xs" data-title="${c.title}">نمایش</button>`;
              cdnResults.appendChild(row);
           });
           if(!cats.length){ cdnResults.innerHTML='<p class="text-center text-gray-400">موردی یافت نشد.</p>'; }
        };

        renderCategories(catGroups);

        cdnSearch.addEventListener('input',()=>{
           const term=cdnSearch.value.trim().toLowerCase();
           const filtered=catGroups.filter(c=>c.title.toLowerCase().includes(term));
           renderCategories(filtered);
        });

        // هندل کلیک نمایش دسته
        cdnResults.addEventListener('click',e=>{
           const btn=e.target.closest('.show-cat');
           if(!btn) return;
           const title=btn.dataset.title;
           const group=catGroups.find(c=>c.title===title);
           if(!group) return;
           cdnResults.innerHTML='';
           const back=document.createElement('button');
           back.className='mb-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gradient-to-r from-gray-700 to-gray-600 hover:from-purple-600 hover:to-indigo-600 text-xs text-white shadow ring-1 ring-white/10 transition-transform transform hover:-translate-x-0.5';
           back.innerHTML='<i data-feather="chevron-right" class="w-4 h-4 opacity-90"></i><span>بازگشت</span>';
           cdnResults.appendChild(back);
           if(window.feather){ feather.replace(); }
           back.addEventListener('click',()=>renderCategories(catGroups));

           group.items.forEach(r=>{
              if(!r.files.length) return;
              const header=document.createElement('div');
              header.className='flex items-center justify-between mt-4 mb-1';
              header.innerHTML=`<div class="text-purple-300 font-semibold flex items-center gap-2">
                  ${r.logo? `<img src="${r.logo}" alt="logo" class="w-5 h-5 rounded">` : ''}
                  <span>${r.name}</span>
                </div>
                <span class="text-xs text-gray-400">${r.version||''}</span>`;
              cdnResults.appendChild(header);
              r.files.forEach(fp=>{
                 const row=document.createElement('div');
                 row.className='flex justify-between items-center bg-gray-700 p-3 rounded mb-1';
                 const fname=fp.split('/').pop();
                 const label=(r.labels && r.labels[fname])? r.labels[fname] : fname;
                 row.innerHTML=`<span class="text-sm">${label}</span><button data-id="${r.id}" data-file="${fp}" class="add-res px-3 py-1 bg-purple-600 rounded text-xs hover:bg-purple-700">افزودن</button>`;
                 cdnResults.appendChild(row);
              });
           });
        });

        cdnResults.addEventListener('click',async e=>{
            const btn=e.target.closest('.add-res');
            if(!btn) return;
            const id=btn.dataset.id;
            const file=btn.dataset.file;
            btn.disabled=true;
            const fd=new FormData();fd.append('action','add');fd.append('resource_id',id);fd.append('file_path',file);
            const resp=await fetch('api/user_library.php',{method:'POST',body:fd});
            const d=await resp.json();
            if(d.success){
                cdnMsg.textContent='با موفقیت اضافه شد';
                setTimeout(()=>location.reload(),700);
            }else{
                cdnMsg.textContent=d.message||'خطا';
                btn.disabled=false;
            }
        });

        // عملیات روی کتابخانه های کاربر
        document.addEventListener('click',async e=>{
            const delBtn=e.target.closest('.del-lib');
            if(delBtn){
                if(!(await showConfirm('حذف شود؟','delete'))) return;
                const fd=new FormData(); fd.append('action','delete'); fd.append('id',delBtn.dataset.id);
                const resp=await fetch('api/user_library.php',{method:'POST',body:fd});
                const d=await resp.json();
                if(d.success){location.reload();} else showAlert(d.message||'خطا');
            }

            const linkBtn=e.target.closest('.get-link');
            if(linkBtn){
                const path=linkBtn.dataset.path;
                const userToken=JSON.parse(document.getElementById('userToken').textContent);
                const url='https://cdnz.ir/'+path+'?t='+userToken;
                const copy=async txt=>{
                    if(navigator.clipboard && window.isSecureContext){
                        try{await navigator.clipboard.writeText(txt);return true;}catch{ /* ignore */ }
                    }
                    // fallback
                    const ta=document.createElement('textarea');
                    ta.value=txt;ta.style.position='fixed';ta.style.opacity='0';
                    document.body.appendChild(ta);ta.select();
                    try{document.execCommand('copy');return true;}catch{return false;}finally{ta.remove();}
                };
                copy(url).then(()=>showAlert('لینک کپی شد!','success')).catch(()=>showAlert('نتوانستم لینک را در کلیپ‌بورد قرار دهم','error'));
            }
        });

        // Token management UI
        const tokValue=document.getElementById('tokValue');
        const tokSample=document.getElementById('tokSample');
        async function loadToken(){
          try{const d=await fetch('api/token.php?action=get').then(r=>r.json());
            if(d.success){
              tokValue.textContent = d.token ? d.token : '—';
              const base=location.origin.replace(/\/$/,'');
              tokSample.textContent = d.token ? base+'/cdn/...?...?t='+d.token : 'برای دریافت لینک، توکن بسازید';
            }
          }catch{}
        }
        loadToken();
        document.getElementById('tokRotate')?.addEventListener('click',async()=>{ const d=await fetch('api/token.php?action=rotate',{method:'POST'}).then(r=>r.json()); if(d.success){ loadToken(); showAlert('توکن جدید ایجاد شد','success'); }});
        document.getElementById('tokRevoke')?.addEventListener('click',async()=>{ if(!(await showConfirm('توکن حذف شود؟','delete'))) return; const d=await fetch('api/token.php?action=revoke',{method:'POST'}).then(r=>r.json()); if(d.success){ loadToken(); showAlert('توکن لغو شد','success'); }});
    </script>
</body>
</html> 