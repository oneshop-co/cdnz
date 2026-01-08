<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// فقط کاربر ID = 1 ادمین است (می‌توانید منطق را تغییر دهید)
if ((int)$_SESSION['user_id'] !== 1) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/api/db.php';

// اطمینان از وجود جدول resources برای جلوگیری از خطا هنگام نمایش صفحه
$pdo->exec("CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    version VARCHAR(50) NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    original_url TEXT NOT NULL,
    local_path VARCHAR(255) NOT NULL,
    category_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// اطمینان از وجود ستون category_id
$chkCat=$pdo->query("SHOW COLUMNS FROM resources LIKE 'category_id'");
if(!$chkCat->rowCount()){
    $pdo->exec("ALTER TABLE resources ADD COLUMN category_id INT NULL, ADD INDEX (category_id)");
}

// اطمینان از وجود جدول resource_categories
$pdo->exec("CREATE TABLE IF NOT EXISTS resource_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// اگر ستون banned در جدول users وجود ندارد اضافه کن
$chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'banned'");
if($chk && $chk->rowCount()==0){
    $pdo->exec("ALTER TABLE users ADD COLUMN banned TINYINT(1) DEFAULT 0");
}

// بعد از افزودن column resources earlier, add banned column check
$colCheck=$pdo->query("SHOW COLUMNS FROM users LIKE 'banned'");
if(!$colCheck->rowCount()){
    $pdo->exec("ALTER TABLE users ADD COLUMN banned TINYINT(1) DEFAULT 0");
}

// اگر ستون version وجود ندارد اضافه کن
$chkVer=$pdo->query("SHOW COLUMNS FROM resources LIKE 'version'");
if($chkVer && $chkVer->rowCount()==0){
    $pdo->exec("ALTER TABLE resources ADD COLUMN version VARCHAR(50) NOT NULL DEFAULT '1.0.0'");
}

// دریافت اطلاعات ادمین
$stmt = $pdo->prepare('SELECT name FROM users WHERE id = 1');
$stmt->execute();
$admin = $stmt->fetch();

// اطمینان از وجود جداول پلن و اشتراک
$pdo->exec("CREATE TABLE IF NOT EXISTS plans (
 id INT PRIMARY KEY,
 name VARCHAR(50),
 price INT,
 limit_bytes BIGINT UNSIGNED
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS user_subscriptions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 plan_id INT NOT NULL,
 expires_at DATETIME,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// دریافت لیست کاربران به همراه پلن و محدودیت پهنای باند
$users = $pdo->query("SELECT u.id, u.name, u.email, u.created_at, u.banned,
       COALESCE((SELECT CONVERT(p.name USING utf8mb4) FROM user_subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.user_id = u.id AND s.expires_at > NOW() ORDER BY s.expires_at DESC LIMIT 1), _utf8mb4'رایگان') AS plan_name,
       COALESCE((SELECT p.limit_bytes FROM user_subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.user_id = u.id AND s.expires_at > NOW() ORDER BY s.expires_at DESC LIMIT 1), 1073741824) AS plan_limit,
       (SELECT s.expires_at FROM user_subscriptions s WHERE s.user_id = u.id AND s.expires_at > NOW() ORDER BY s.expires_at DESC LIMIT 1) AS plan_expire
    FROM users u
    ORDER BY u.id DESC")->fetchAll();

// محاسبه مصرف پهنای باند هر کاربر در ۳۰ روز گذشته
$since30 = date('Y-m-d H:i:s', strtotime('-30 days'));
function fmtBytes($b){$u=['B','KB','MB','GB','TB'];if($b<=0)return ['0','B'];$pow=min(floor(log($b,1024)),count($u)-1);return [round($b/pow(1024,$pow),($pow?2:0)),$u[$pow]];}
foreach($users as &$u){
    $stmt=$pdo->prepare('SELECT COALESCE(SUM(bytes),0) FROM user_bandwidth WHERE user_id=? AND served_at>=?');
    $stmt->execute([$u['id'],$since30]);
    $used=(int)$stmt->fetchColumn();
    $u['used_bytes']=$used;
    [$uv,$uu]=fmtBytes($used); $u['used_fmt']="$uv&nbsp;$uu";
    [$lv,$lu]=fmtBytes($u['plan_limit']); $u['limit_fmt']="$lv&nbsp;$lu";
    $u['percent']=$u['plan_limit']?min(100,($used/$u['plan_limit'])*100):0;
    // expiry
    if (!empty($u['plan_expire'])) {
        $expTs = strtotime($u['plan_expire']);
        $daysLeft = max(0, (int)ceil(($expTs - time())/86400));
        $u['expire_days'] = $daysLeft;
        $u['expire_fmt'] = date('Y/m/d', $expTs);
    } else {
        $u['expire_days'] = null;
        $u['expire_fmt'] = null;
    }
}
unset($u);

// add after fetching settings near user info
$pdo->exec("CREATE TABLE IF NOT EXISTS settings(`key` VARCHAR(50) PRIMARY KEY, `value` TEXT NOT NULL)");
$settings=$pdo->query('SELECT * FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل ادمین | CDNz</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="پنل مدیریت CDNz برای مدیریت منابع و کاربران.">
    <link rel="canonical" href="https://cdnz.ir/admin">
    <!-- Site Icon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/uploads/icons/cdnz.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/uploads/icons/cdnz.png">
    <meta property="og:type" content="website">
    <meta property="og:title" content="پنل ادمین CDNz">
    <meta property="og:description" content="مدیریت منابع CDN و کاربران.">
    <meta property="og:url" content="https://cdnz.ir/admin">
    <meta property="og:image" content="https://cdnz.ir/assets/og-admin.png">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/arbasirian/iransans/iran-sans.css">
    <style>
        html, body, .font-sans {font-family: 'IRANSans', 'IranSans', 'IRANSansWeb', sans-serif !important;}
    </style>
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-900 text-white font-sans min-h-screen">
    <!-- ناوبری ادمین -->
    <nav class="w-full bg-gradient-to-b from-gray-900/90 to-gray-900/60 backdrop-blur-md border-b border-gray-800 fixed top-0 z-20">
        <div class="max-w-7xl mx-auto px-6 md:px-10 py-3 flex items-center justify-between">
            <a href="index.php#home" class="text-2xl font-black">CDNz</a>
            <div class="flex items-center gap-6 text-gray-300">
                <a href="dashboard.php" class="nav-link hidden md:inline">داشبورد کاربر</a>
                <a href="admin.php" class="font-bold text-purple-400">پنل ادمین</a>
                <a href="api/auth.php?action=logout" class="px-4 py-2 bg-purple-600 rounded hover:bg-purple-700">خروج</a>
            </div>
        </div>
    </nav>

    <main class="pt-24 pb-32 max-w-6xl mx-auto px-6 md:px-10">
        <h1 class="text-3xl md:text-4xl font-extrabold mb-8">سلام ادمین <?php echo htmlspecialchars($admin['name']); ?> 👑</h1>

        <!-- بخش منابع -->
        <section id="resources" class="tab-section bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg mb-12">
            <h2 class="text-2xl font-bold mb-4">منابع</h2>
            <div class="flex flex-wrap gap-4 mb-6">
               <button id="addResourceBtn" class="px-6 py-2 bg-purple-600 rounded hover:bg-purple-700">افزودن منبع</button>
               <button id="resCatManage" class="px-6 py-2 bg-blue-600 rounded hover:bg-blue-700">دسته بندی ها</button>
            </div>
            <div id="addResourceModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-30">
                <div class="bg-gray-800 border border-purple-700 shadow-2xl rounded-2xl p-8 w-full max-w-3xl overflow-y-auto max-h-[90vh]">
                    <h3 id="modalTitle" class="text-xl font-bold mb-4">افزودن منبع جدید</h3>
                    <form id="addResourceForm" class="space-y-4" enctype="multipart/form-data">
                        <input type="hidden" name="id" id="resource_id">
                        <div class="grid md:grid-cols-3 gap-4">
                            <input name="name" type="text" placeholder="عنوان منبع" required class="w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600">
                            <input name="logo" type="url" placeholder="لینک لوگو (اختیاری)" class="w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600">
                            <input name="version" id="resource_version" type="text" placeholder="نسخه (مثلاً 5.3.2)" required class="w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600">
                            <select name="category_id" class="w-full p-3 rounded bg-gray-700 text-gray-200 focus:ring-2 focus:ring-purple-600">
                                <option value="">بدون دسته</option>
                                <?php $rcats = $pdo->query('SELECT id,title FROM resource_categories ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC); foreach($rcats as $c):?>
                                    <option value="<?=$c['id']?>"><?=htmlspecialchars($c['title'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2 flex items-center gap-4 text-sm">
                            <label class="flex items-center gap-2"><input type="radio" name="resMode" value="link" checked> افزودن با لینک</label>
                            <label class="flex items-center gap-2"><input type="radio" name="resMode" value="upload"> آپلود فایل</label>
                        </div>

                        <div id="linkContainer" class="space-y-4"></div>
                        <button type="button" id="addLinkRow" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-700">افزودن لینک</button>

                        <div id="uploadSection" class="hidden space-y-3">
                            <input type="file" name="files[]" id="resFiles" multiple accept=".js,.css,.map,.json,.txt,.svg,.png,.jpg,.jpeg,.webp" class="w-full text-sm">
                            <p class="text-xs text-gray-400">می‌توانید چند فایل انتخاب کنید. فایل‌ها در مسیر CDN منبع ذخیره می‌شوند.</p>
                        </div>

                        <div class="flex justify-end gap-4 pt-2">
                            <button id="closeModalBtn" type="button" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-700">انصراف</button>
                            <button type="submit" class="px-6 py-2 bg-purple-600 rounded hover:bg-purple-700">ذخیره</button>
                        </div>
                        <p id="resMsg" class="text-sm mt-2"></p>
                    </form>
                </div>
            </div>

            <!-- مودال مدیریت دسته بندی منابع -->
            <div id="resCatModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-40 px-4">
              <div class="bg-gray-800 rounded-2xl shadow-2xl ring-1 ring-blue-500/30 p-6 w-full max-w-md">
                 <h3 class="text-xl font-bold text-center text-blue-400 mb-4">دسته بندی های منابع</h3>
                 <div id="resCatList" class="space-y-2 max-h-60 overflow-y-auto mb-4">
                    <?php foreach($rcats as $c): ?>
                       <div class="flex justify-between items-center bg-gray-700 rounded px-3 py-1">
                          <span class="text-sm flex-1"><?=htmlspecialchars($c['title'])?></span>
                          <button class="show-rescat text-blue-400 text-xs ml-2" data-id="<?=$c['id']?>">نمایش</button>
                          <button class="edit-rescat text-green-400 text-xs ml-2" data-id="<?=$c['id']?>" data-title="<?=htmlspecialchars($c['title'],ENT_QUOTES)?>">ویرایش</button>
                          <button class="del-rescat text-red-400 text-xs" data-id="<?=$c['id']?>">حذف</button>
                       </div>
                    <?php endforeach; ?>
                    <?php if(empty($rcats)): ?><p class="text-center text-gray-400 text-sm">دسته ای وجود ندارد.</p><?php endif; ?>
                 </div>
                 <div class="space-y-2">
                 <input id="newResCat" type="text" placeholder="عنوان دسته" class="w-full p-2 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-blue-600">
                 <button id="addResCat" class="w-full px-3 py-2 bg-blue-600 hover:bg-blue-700 rounded text-xs">افزودن</button>
                 </div>
                 <p id="resCatMsg" class="text-sm mt-2"></p>
              </div>
            </div>

            <!-- مودال ویرایش کاربر -->
            <div id="userModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-30">
                <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
                    <h3 class="text-xl font-bold mb-4">ویرایش کاربر</h3>
                    <form id="editUserForm" class="space-y-4">
                        <input type="hidden" name="id" id="user_id">
                        <input name="name" id="user_name" type="text" placeholder="نام" required class="w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600">
                        <input name="email" id="user_email" type="email" placeholder="ایمیل" required class="w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600">
                        <input name="password" id="user_password" type="password" placeholder="رمز عبور جدید (اختیاری)" class="w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600">
                        <div class="flex justify-end gap-4 pt-2">
                            <button type="button" id="userCancel" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-700">انصراف</button>
                            <button type="submit" class="px-6 py-2 bg-purple-600 rounded hover:bg-purple-700">ذخیره</button>
                        </div>
                        <p id="userMsg" class="text-sm mt-2"></p>
                    </form>
                </div>
            </div>

            <?php
            $resources = $pdo->query('SELECT id, name, logo, version, local_path, original_url, category_id, created_at FROM resources ORDER BY id DESC')->fetchAll();
            ?>
            <!-- لیست موبایل برای منابع -->
            <div class="space-y-4 sm:hidden">
            <?php foreach($resources as $r): ?>
                <div class="bg-gray-700/60 border border-gray-600 rounded-lg p-4 flex justify-between items-start shadow-md">
                    <div class="text-right">
                        <p class="font-semibold text-purple-200 text-sm mb-1 line-clamp-1"><?=htmlspecialchars($r['name'])?></p>
                        <p class="text-xs text-gray-400 mb-1 line-clamp-1">/<?=htmlspecialchars($r['local_path'])?></p>
                        <p class="text-xs text-gray-500 mb-1">نسخه: <?=htmlspecialchars($r['version'])?></p>
                        <p class="text-xs text-gray-500"><?=date('Y/m/d',strtotime($r['created_at']))?></p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <button class="edit-res flex items-center gap-1 w-24 justify-center px-3 py-1.5 rounded-full text-xs font-semibold shadow-md transition bg-gradient-to-r from-blue-500 to-indigo-600 hover:to-indigo-700" data-id="<?=$r['id']?>" data-name="<?=htmlspecialchars($r['name'])?>" data-logo="<?=htmlspecialchars($r['logo'])?>" data-version="<?=htmlspecialchars($r['version'])?>" data-urls='<?=htmlspecialchars($r['original_url'],ENT_QUOTES|ENT_SUBSTITUTE)?>' data-cat="<?= (int)$r['category_id']?>"><span>ویرایش</span></button>
                        <button class="del-res flex items-center gap-1 w-24 justify-center px-3 py-1.5 rounded-full text-xs font-semibold shadow-md transition bg-gradient-to-r from-red-600 to-rose-600 hover:to-rose-700" data-id="<?=$r['id']?>"><span>حذف</span></button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if(empty($resources)): ?><p class="text-center text-gray-500 text-sm">منبعی وجود ندارد.</p><?php endif; ?>
            </div>

            <div class="overflow-x-auto hidden sm:block">
                <table class="min-w-full text-right">
                    <thead>
                        <tr class="text-purple-400">
                            <th class="py-2">نام</th><th>لوگو</th><th>نسخه</th><th>مسیر</th><th>تاریخ</th><th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php foreach($resources as $r): ?>
                        <tr class="border-t border-gray-700">
                            <td class="py-2 px-2"><?=htmlspecialchars($r['name'])?></td>
                            <td class="py-2 px-2"><?php if($r['logo']): ?><img src="<?=htmlspecialchars($r['logo'])?>" alt="logo" class="w-6 h-6 inline"><?php endif;?></td>
                            <td class="py-2 px-2 text-xs"><?=htmlspecialchars($r['version']??'—')?></td>
                            <td class="py-2 px-2 text-xs">/<?=htmlspecialchars($r['local_path'])?></td>
                            <td class="py-2 px-2"><?=date('Y/m/d',strtotime($r['created_at']))?></td>
                            <td class="py-2 px-2 flex gap-2">
                                <button class="edit-res px-3 py-1.5 bg-gradient-to-r from-blue-500 to-indigo-600 hover:to-indigo-700 rounded-full text-xs font-semibold shadow-md transition" data-id="<?=$r['id']?>" data-name="<?=htmlspecialchars($r['name'])?>" data-logo="<?=htmlspecialchars($r['logo'])?>" data-version="<?=htmlspecialchars($r['version'])?>" data-urls='<?=htmlspecialchars($r['original_url'],ENT_QUOTES|ENT_SUBSTITUTE)?>' data-cat="<?= (int)$r['category_id']?>">ویرایش</button>
                                <button class="del-res px-3 py-1.5 bg-gradient-to-r from-red-600 to-rose-600 hover:to-rose-700 rounded-full text-xs font-semibold shadow-md transition" data-id="<?=$r['id']?>">حذف</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php
// اطمینان از وجود جدول snippets
$pdo->exec("CREATE TABLE IF NOT EXISTS snippets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  language VARCHAR(50) DEFAULT 'text',
  code TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// اطمینان از وجود جدول دسته بندی‌ها
$pdo->exec("CREATE TABLE IF NOT EXISTS snippet_categories(
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// دریافت دسته‌بندی‌ها
$cats = $pdo->query('SELECT id,title FROM snippet_categories ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

// دریافت نمونه کدها
$snips = $pdo->query('SELECT id,title,language,code,created_at FROM snippets ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
        <section id="snippets" class="tab-section hidden bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg mb-12">
            <h2 class="text-2xl font-bold mb-4">نمونه‌کدها</h2>
            <div class="flex flex-wrap gap-4 mb-6">
            <button id="addSnippetBtn" class="px-6 py-2 bg-purple-600 rounded hover:bg-purple-700">افزودن نمونه کد</button>
            <button id="catManage" class="px-6 py-2 bg-blue-600 rounded hover:bg-blue-700">مدیریت دسته‌ها</button>
            </div>

            <!-- مودال مدیریت دسته‌ها -->
            <div id="catModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-40 px-4">
              <div class="bg-gray-800 rounded-2xl shadow-2xl ring-1 ring-blue-500/30 p-6 w-full max-w-md">
                 <h3 class="text-xl font-bold text-center text-blue-400 mb-4">دسته‌بندی‌ها</h3>
                 <div id="catList" class="space-y-2 max-h-60 overflow-y-auto mb-4">
                    <?php foreach($cats as $c): ?>
                       <div class="flex justify-between items-center bg-gray-700 rounded px-3 py-1">
                          <span class="text-sm"><?=htmlspecialchars($c['title'])?></span>
                          <button class="del-cat text-red-400 text-xs" data-id="<?=$c['id']?>">حذف</button>
                       </div>
                    <?php endforeach; ?>
                    <?php if(empty($cats)): ?><p class="text-center text-gray-400 text-sm">دسته‌ای وجود ندارد.</p><?php endif; ?>
                 </div>
                 <div class="space-y-2">
                 <input id="newCat" type="text" placeholder="عنوان دسته" class="w-full p-2 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-blue-600">
                 <input id="catImg" type="file" accept="image/*" class="w-full p-2 rounded bg-gray-700 text-gray-200 focus:ring-2 focus:ring-blue-600">
                 <button id="addCat" class="w-full px-3 py-2 bg-blue-600 hover:bg-blue-700 rounded text-xs">افزودن</button>
                 </div>
                 <p id="catMsg" class="text-sm mt-2"></p>
              </div>
            </div>

            <!-- مودال افزودن نمونه کد -->
            <div id="snippetModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-40 px-4">
              <div class="bg-gray-800 rounded-2xl shadow-2xl ring-1 ring-purple-500/30 p-8 w-full max-w-lg">
                 <h3 class="text-2xl font-bold text-center text-purple-400 mb-6">افزودن نمونه کد</h3>
                 <form id="snippetForm" class="space-y-4">
                    <input type="text" name="title" placeholder="موضوع" required class="w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600">
                    <textarea name="description" placeholder="توضیحات" rows="3" class="w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600"></textarea>
                    <textarea name="code" placeholder="کد" rows="6" required class="w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600"></textarea>
                    <input type="file" name="image" accept="image/*" class="w-full p-3 rounded bg-gray-700 text-gray-200 focus:ring-2 focus:ring-purple-600">
                    <select name="category_id" class="w-full p-3 rounded bg-gray-700 text-gray-200 focus:ring-2 focus:ring-purple-600">
                       <option value="">بدون دسته</option>
                       <?php foreach($cats as $c):?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['title'])?></option><?php endforeach; ?>
                    </select>
                    <input type="text" name="tags" placeholder="برچسب‌ها (با ویرگول جدا)" class="w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600">
                    <div class="flex justify-end gap-4 pt-2">
                       <button type="button" id="snippetCancel" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-700">انصراف</button>
                       <button type="submit" class="px-6 py-2 bg-purple-600 rounded hover:bg-purple-700">ذخیره</button>
                    </div>
                    <p id="snipMsg" class="text-sm mt-2"></p>
                 </form>
              </div>
            </div>

            <!-- لیست کارت موبایل -->
            <div class="space-y-4 sm:hidden">
            <?php foreach($snips as $sn): ?>
                <div class="bg-gray-700/60 border border-gray-600 rounded-lg p-4 shadow-md">
                    <div class="flex justify-between items-start mb-2">
                        <p class="font-semibold text-purple-200 text-sm line-clamp-1"><?=htmlspecialchars($sn['title'])?></p>
                        <button class="del-snip flex items-center gap-1 w-20 justify-center px-2 py-1 bg-gradient-to-r from-red-600 to-rose-600 hover:to-rose-700 rounded-full text-xs font-semibold shadow-md transition" data-id="<?=$sn['id']?>">حذف</button>
                    </div>
                    <pre class="text-xs text-gray-300 overflow-x-auto max-h-32"><?=htmlspecialchars($sn['code'])?></pre>
                </div>
            <?php endforeach; ?>
            <?php if(empty($snips)): ?><p class="text-center text-gray-500 text-sm">هنوز نمونه‌کدی ثبت نشده.</p><?php endif; ?>
            </div>

            <div class="overflow-x-auto hidden sm:block">
                <table class="min-w-full text-right divide-y divide-gray-700">
                    <thead class="bg-gray-900/70"><tr class="text-purple-400 text-sm"><th class="py-2 px-2">عنوان</th><th class="py-2 px-2">زبان</th><th class="py-2 px-2">تاریخ</th><th class="py-2 px-2">عملیات</th></tr></thead>
                    <tbody class="text-gray-300 text-sm">
                    <?php foreach($snips as $sn): ?>
                        <tr class="border-t border-gray-700 hover:bg-gray-700/30"><td class="py-2 px-2 max-w-xs line-clamp-1" title="<?=htmlspecialchars($sn['title'])?>"><?=htmlspecialchars($sn['title'])?></td><td class="py-2 px-2 text-xs"><?=htmlspecialchars($sn['language'])?></td><td class="py-2 px-2 text-xs"><?=date('Y/m/d',strtotime($sn['created_at']))?></td><td class="py-2 px-2"><button class="del-snip px-3 py-1.5 bg-gradient-to-r from-red-600 to-rose-600 hover:to-rose-700 rounded-full text-xs font-semibold shadow-md transition" data-id="<?=$sn['id']?>">حذف</button></td></tr>
                    <?php endforeach; ?>
                    <?php if(empty($snips)): ?><tr><td colspan="4" class="py-4 text-center text-gray-500">نمونه‌کدی ثبت نشده.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

<section id="users" class="tab-section hidden bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg overflow-x-auto">
            <h2 class="text-2xl font-bold mb-4">لیست کاربران</h2>
            <!-- لیست کارت در موبایل -->
            <div class="space-y-4 sm:hidden">
            <?php foreach($users as $u): ?>
                <div class="bg-gray-700/60 border border-gray-600 rounded-lg p-4 flex justify-between items-start shadow-md">
                    <div class="text-right">
                        <p class="font-semibold text-purple-200 text-sm mb-1">#<?=$u['id']?> — <?=htmlspecialchars($u['name'])?></p>
                        <p class="text-xs text-gray-400 mb-1 line-clamp-1"><?=htmlspecialchars($u['email'])?></p>
                        <p class="text-xs text-gray-500 mb-1">عضویت: <?=date('Y/m/d',strtotime($u['created_at']))?></p>
                        <p class="text-xs text-purple-300 mb-1">پلن: <?=htmlspecialchars($u['plan_name'])?></p>
                        <p class="text-xs text-gray-400 mb-1">مصرف: <?=$u['used_fmt']?> / <?=$u['limit_fmt']?></p>
                        <p class="text-xs <?=(isset($u['expire_days']) && $u['expire_days']!==null && $u['expire_days']<=5)?'text-red-400':'text-gray-300'?>">
                          پایان اشتراک: <?= $u['expire_fmt']? htmlspecialchars($u['expire_fmt']) : '—' ?>
                          <?php if(isset($u['expire_days']) && $u['expire_days']!==null): ?>
                            (<?= $u['expire_days'] ?> روز مانده)
                          <?php endif; ?>
                        </p>
                        <p class="text-xs <?= $u['banned']? 'text-red-400':'text-green-400' ?>">وضعیت: <?= $u['banned']?'مسدود':'فعال' ?></p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <button class="edit-user flex items-center gap-1 w-24 justify-center px-3 py-1.5 rounded-full text-xs font-semibold shadow-md transition bg-gradient-to-r from-blue-500 to-indigo-600 hover:to-indigo-700" data-id="<?=$u['id']?>" data-name="<?=htmlspecialchars($u['name'])?>" data-email="<?=htmlspecialchars($u['email'])?>">ویرایش</button>
                        <button class="set-plan flex items-center gap-1 w-24 justify-center px-3 py-1.5 rounded-full text-xs font-semibold shadow-md transition bg-gradient-to-r from-purple-600 to-pink-600 hover:to-pink-700" data-id="<?=$u['id']?>">پلن</button>
                        <button class="ban-user flex items-center gap-1 w-24 justify-center px-3 py-1.5 rounded-full text-xs font-semibold shadow-md transition bg-gradient-to-r from-amber-500 to-orange-600 hover:to-orange-700" data-id="<?=$u['id']?>" data-state="<?=$u['banned']?>"><?=$u['banned']?'رفع‌بن':'بن'?></button>
                        <?php if($u['id']!=1): ?>
                        <button class="del-user flex items-center gap-1 w-24 justify-center px-3 py-1.5 rounded-full text-xs font-semibold shadow-md transition bg-gradient-to-r from-red-600 to-rose-600 hover:to-rose-700" data-id="<?=$u['id']?>">حذف</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if(empty($users)): ?><p class="text-center text-gray-500 text-sm">کاربری یافت نشد.</p><?php endif; ?>
            </div>

            <div class="overflow-x-auto hidden sm:block">
            <table class="min-w-full text-right">
                <thead>
                    <tr class="text-purple-400">
                        <th class="py-2">#</th>
                        <th class="py-2">نام</th>
                        <th class="py-2">ایمیل</th>
                        <th class="py-2">تاریخ عضویت</th>
                        <th class="py-2">پلن</th><th class="py-2">مصرف</th><th class="py-2">پایان اشتراک</th><th class="py-2">وضعیت</th>
                        <th class="py-2">عملیات</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php foreach ($users as $u): ?>
                        <tr class="border-t border-gray-700 hover:bg-gray-700/30">
                            <td class="py-2 px-2"><?php echo $u['id']; ?></td>
                            <td class="py-2 px-2"><?php echo htmlspecialchars($u['name']); ?></td>
                            <td class="py-2 px-2"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td class="py-2 px-2"><?php echo date('Y/m/d', strtotime($u['created_at'])); ?></td>
                            <td class="py-2 px-2 text-xs"><?=htmlspecialchars($u['plan_name'])?></td>
                            <td class="py-2 px-2 text-xs"><?=$u['used_fmt']?> / <?=$u['limit_fmt']?></td>
                            <td class="py-2 px-2 text-xs <?=(isset($u['expire_days']) && $u['expire_days']!==null && $u['expire_days']<=5)?'text-red-400':'text-gray-300'?>">
                                <?= $u['expire_fmt']? htmlspecialchars($u['expire_fmt']) : '—' ?>
                                <?php if(isset($u['expire_days']) && $u['expire_days']!==null): ?>
                                  <span class="text-gray-400">(<?= $u['expire_days'] ?> روز مانده)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-2 text-sm"><?= $u['banned']?'مسدود':'فعال' ?></td>
                            <td class="py-2 px-2 flex gap-2">
                                <button class="edit-user px-3 py-1.5 bg-gradient-to-r from-blue-500 to-indigo-600 hover:to-indigo-700 rounded-full text-xs font-semibold shadow-md transition" data-id="<?=$u['id']?>" data-name="<?=htmlspecialchars($u['name'])?>" data-email="<?=htmlspecialchars($u['email'])?>">ویرایش</button>
                                <button class="set-plan px-3 py-1.5 bg-gradient-to-r from-purple-600 to-pink-600 hover:to-pink-700 rounded-full text-xs font-semibold shadow-md transition" data-id="<?=$u['id']?>">پلن</button>
                                <button class="ban-user px-3 py-1.5 bg-gradient-to-r from-amber-500 to-orange-600 hover:to-orange-700 rounded-full text-xs font-semibold shadow-md transition" data-id="<?=$u['id']?>" data-state="<?=$u['banned']?>"><?= $u['banned']?'رفع‌بن':'بن' ?></button>
                                <?php if($u['id']!=1): ?>
                                <button class="del-user px-3 py-1.5 bg-gradient-to-r from-red-600 to-rose-600 hover:to-rose-700 rounded-full text-xs font-semibold shadow-md transition" data-id="<?=$u['id']?>">حذف</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <!-- بخش تنظیمات -->
        <section id="settings" class="tab-section hidden mt-12 bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
            <h2 class="text-2xl font-bold mb-6">تنظیمات سامانه</h2>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block mb-2 text-gray-300">حد رایگان (بایت)</label>
                    <input id="setFree" type="number" value="<?=$settings['free_limit']??5368709120?>" class="w-full p-3 rounded bg-gray-700 text-gray-200 focus:ring-2 focus:ring-purple-600">
                </div>
                <div>
                    <label class="block mb-2 text-gray-300">مرچنت آی‌دی زرین‌پال</label>
                    <input id="setMid" type="text" value="<?=$settings['merchant_id']??''?>" class="w-full p-3 rounded bg-gray-700 text-gray-200 focus:ring-2 focus:ring-purple-600">
                </div>
            </div>
            <button id="saveSettings" class="mt-6 px-6 py-2 bg-purple-600 rounded hover:bg-purple-700">ذخیره</button>
            <p id="setMsg" class="text-sm mt-4"></p>
        </section>

        <!-- بخش دسته -->
        <?php
          $pdo->exec("CREATE TABLE IF NOT EXISTS requests (
             id INT AUTO_INCREMENT PRIMARY KEY,
             user_id INT NOT NULL,
             message TEXT NOT NULL,
             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
          ) ENGINE=InnoDB CHARSET=utf8mb4;");
          $reqs=$pdo->query('SELECT r.*,u.name FROM requests r JOIN users u ON u.id=r.user_id ORDER BY r.id DESC')->fetchAll();
        ?>
        <section id="requests" class="tab-section hidden mt-12 bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg overflow-x-auto">
            <h2 class="text-2xl font-bold mb-4">درخواست‌های کاربران</h2>
            <table class="min-w-full text-right">
              <thead><tr class="text-purple-400"><th class="py-2 px-2">#</th><th>کاربر</th><th>متن درخواست</th><th>تاریخ</th><th>عملیات</th></tr></thead>
              <tbody class="text-gray-300">
              <?php foreach($reqs as $rq): ?>
                 <tr class="border-t border-gray-700"><td class="py-2 px-2"><?=$rq['id']?></td><td class="py-2 px-2"><?=htmlspecialchars($rq['name'])?></td><td class="py-2 px-2 text-sm line-clamp-1 max-w-xs" title="<?=htmlspecialchars($rq['message'])?>"><?=htmlspecialchars(mb_strimwidth($rq['message'],0,40,'...'))?></td><td class="py-2 px-2 text-xs"><?=date('Y/m/d',strtotime($rq['created_at']))?></td><td class="py-2 px-2 flex gap-2"><button class="view-req px-3 py-1.5 bg-gradient-to-r from-blue-500 to-indigo-600 hover:to-indigo-700 rounded-full text-xs font-semibold shadow-md transition" data-msg="<?=htmlspecialchars($rq['message'])?>">بررسی</button><button class="del-req px-3 py-1.5 bg-gradient-to-r from-red-600 to-rose-600 hover:to-rose-700 rounded-full text-xs font-semibold shadow-md transition" data-id="<?=$rq['id']?>">حذف</button></td></tr>
              <?php endforeach; ?>
              <?php if(empty($reqs)): ?><tr><td colspan="4" class="py-4 text-center text-gray-500">درخواستی ثبت نشده.</td></tr><?php endif; ?>
              </tbody>
            </table>
        </section>
    </main>

    <!-- ناوبری شناور پایین -->
    <nav class="fixed bottom-6 inset-x-0 z-20 flex justify-center pointer-events-none">
        <div class="bg-gray-800 border border-gray-700 rounded-2xl shadow-lg px-6 py-2 pointer-events-auto">
        <ul class="flex gap-8 text-xs" id="bottomNav">
            <li>
                <a href="#" data-target="requests" class="nav-btn flex flex-col items-center gap-1 text-gray-300 hover:text-purple-400">
                    <i data-feather="inbox" class="w-5 h-5"></i>
                    درخواست‌ها
                </a>
            </li>
            <li>
                <a href="#" data-target="resources" class="nav-btn flex flex-col items-center gap-1 text-purple-400">
                    <i data-feather="layers" class="w-5 h-5"></i>
                    منابع
                </a>
            </li>
                                <li>
                        <a href="#" data-target="snippets" class="nav-btn flex flex-col items-center gap-1 text-gray-300 hover:text-purple-400">
                            <i data-feather="code" class="w-5 h-5"></i>
                            نمونه‌کدها
                        </a>
                    </li>
                    <li>
                        <a href="#" data-target="users" class="nav-btn flex flex-col items-center gap-1 text-gray-300 hover:text-purple-400">
                    <i data-feather="users" class="w-5 h-5"></i>
                    کاربران
                </a>
            </li>
            <li>
                <a href="#" data-target="settings" class="nav-btn flex flex-col items-center gap-1 text-gray-300 hover:text-purple-400">
                    <i data-feather="settings" class="w-5 h-5"></i>
                    تنظیمات
                </a>
            </li>
        </ul>
        </div>
    </nav>

    <!-- مودال انتخاب پلن -->
    <?php $plans=$pdo->query('SELECT id,name FROM plans')->fetchAll(); ?>
    <div id="planModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 px-4">
        <div class="bg-gray-800 rounded-xl p-6 w-full max-w-sm shadow-2xl ring-1 ring-purple-500/30">
            <h3 class="text-xl font-bold mb-4 text-center text-purple-400">تعیین پلن کاربر</h3>
            <input type="hidden" id="plan_user_id">
            <select id="plan_select" class="w-full p-3 rounded bg-gray-700 text-gray-200 mb-6">
               <?php foreach($plans as $pl): ?>
                 <option value="<?=$pl['id']?>"><?=$pl['name']?></option>
               <?php endforeach; ?>
            </select>
            <div class="flex justify-end gap-4">
                <button id="planCancel" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-500">انصراف</button>
                <button id="planSave" class="px-4 py-2 bg-purple-600 rounded hover:bg-purple-700">ذخیره</button>
            </div>
        </div>
    </div>

    <script>
        const RESOURCES_DATA = <?=json_encode($resources, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);?>;
        feather.replace();

        // تابع نمایش مودال پیام سراسری
        const showAlert=(msg,type='info')=>{
            const cfg={
              success:['text-green-400','bg-green-600','check-circle'],
              error:['text-red-400','bg-red-600','alert-triangle'],
              info:['text-purple-400','bg-purple-600','info']
            }[type]||cfg.info;
            const [iconColor,btnColor,iconName]=cfg;

            const overlay=document.createElement('div');
            overlay.className='fixed inset-0 flex items-center justify-center z-50 opacity-0 transition-opacity duration-300 bg-black/70 backdrop-blur-sm';
            overlay.innerHTML=`<div class="alertBox transform scale-90 opacity-0 transition-all duration-300 bg-gray-800 rounded-xl p-8 w-full max-w-sm text-center shadow-2xl ring-1 ring-white/10">
                <div class="flex justify-center mb-4"><i data-feather="${iconName}" class="w-12 h-12 ${iconColor}"></i></div>
                <p class="text-gray-200 mb-6">${msg}</p>
                <button class="alertClose px-6 py-2 ${btnColor} rounded hover:brightness-110 text-sm">باشه</button>
            </div>`;
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
        // تابع تایید با مودال و پرمیس
        const showConfirm=(msg,type='question')=>{
            return new Promise(resolve=>{
                const cfg={
                  delete:['text-red-400','bg-red-600','trash-2'],
                  question:['text-yellow-400','bg-yellow-600','help-circle']
                }[type]||cfg.question;
                const [iconColor,btnColor,iconName]=cfg;
                const overlay=document.createElement('div');
                overlay.className='fixed inset-0 flex items-center justify-center z-50 opacity-0 transition-opacity duration-300 bg-black/70 backdrop-blur-sm';
                overlay.innerHTML=`<div class="confirmBox transform scale-90 opacity-0 transition-all duration-300 bg-gray-800 rounded-xl p-8 w-full max-w-sm text-center shadow-2xl ring-1 ring-white/10">
                    <div class="flex justify-center mb-4"><i data-feather="${iconName}" class="w-12 h-12 ${iconColor}"></i></div>
                    <p class="text-gray-200 mb-6">${msg}</p>
                    <div class="flex justify-center gap-4">
                        <button class="confirmYes px-6 py-2 ${btnColor} rounded hover:brightness-110 text-sm">تایید</button>
                        <button class="confirmNo px-6 py-2 bg-gray-600 rounded hover:bg-gray-500 text-sm">انصراف</button>
                    </div>
                </div>`;
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
        // تب‌ها
        const buttons = document.querySelectorAll('.nav-btn');
        const sections = document.querySelectorAll('.tab-section');
        buttons.forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                const target = btn.dataset.target;
                // فعال/غیرفعال کردن دکمه‌ها
                buttons.forEach(b => b.classList.remove('text-purple-400'));
                btn.classList.add('text-purple-400');
                // نمایش تب مربوطه
                sections.forEach(sec => {
                    sec.classList.toggle('hidden', sec.id !== target);
                });
            });
        });

        // مدال افزودن منبع
        const addResourceBtn = document.getElementById('addResourceBtn');
        const addResourceModal = document.getElementById('addResourceModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const addResourceForm = document.getElementById('addResourceForm');
        const resMsg = document.getElementById('resMsg');

        // 🔽 افزوده شد: متغیرهای مودال و دکمه لغو برای ویرایش کاربر
        const userModal = document.getElementById('userModal');
        const userCancel = document.getElementById('userCancel');

        if(addResourceBtn){
            addResourceBtn.addEventListener('click', () => {
                addResourceModal.classList.remove('hidden');
            });
        }

        if(closeModalBtn){
            closeModalBtn.addEventListener('click', () => {
                addResourceModal.classList.add('hidden');
            });
        }

        if(addResourceForm){
            addResourceForm.addEventListener('submit',async e=>{
                e.preventDefault();
                resMsg.textContent='در حال ارسال...';
                const formData=new FormData(addResourceForm);
                const id=document.getElementById('resource_id').value;
                formData.append('action', id ? 'update':'add');
                const resp=await fetch('api/resource.php',{method:'POST',body:formData});
                const data=await resp.json();
                if(data.success){
                    resMsg.textContent='با موفقیت ذخیره شد!';
                    setTimeout(()=>location.reload(),1000);
                }else{
                    resMsg.textContent=data.message||'خطا';
                }
            });
        }
        // مدیریت ردیف‌های لینک
        const linkContainer=document.getElementById('linkContainer');
        const addLinkRow=document.getElementById('addLinkRow');

        const createRow=(title='',url='')=>{
            const row=document.createElement('div');
            row.className='flex flex-col md:flex-row gap-3 items-center';
            row.innerHTML=`
                <input name="titles[]" type="text" placeholder="عنوان فایل" value="${title}" required class="md:w-1/4 w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600">
                <input name="urls[]" type="url" placeholder="لینک مستقیم فایل" value="${url}" required class="flex-1 w-full p-3 rounded bg-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-purple-600">
                <button type="button" class="remove-row w-full md:w-auto bg-red-600 hover:bg-red-700 text-xs px-4 py-2 rounded">حذف</button>`;
            linkContainer.appendChild(row);
        };

        const resetRows=()=>{
            linkContainer.innerHTML='';
            createRow();
        };

        // باز شدن مودال -> حالت پیشفرض لینک
        addResourceBtn?.addEventListener('click',()=>{
            resetRows();
            linkContainer.classList.remove('hidden');
            addLinkRow.classList.remove('hidden');
            uploadSection.classList.add('hidden');
        });

        addLinkRow?.addEventListener('click',e=>{
            e.preventDefault();
            createRow();
        });

        // تفویض رویداد برای تمامی عملیات (منابع و کاربران)
        document.addEventListener('click',async (e)=>{
            const delRes=e.target.closest('.del-res');
            if(delRes){
               if(!(await showConfirm('حذف شود؟','delete'))) return;
               const fd=new FormData();fd.append('action','delete');fd.append('id',delRes.dataset.id);
               const d=await (await fetch('api/resource.php',{method:'POST',body:fd})).json();
               return d.success?location.reload():showAlert(d.message||'خطا');
            }

            const remRow=e.target.closest('.remove-row');
            if(remRow){
               remRow.parentElement.remove();
               return;
            }

            const editRes=e.target.closest('.edit-res');
            if(editRes){
               const {id,name,logo,version,urls,cat}=editRes.dataset;
               document.getElementById('resource_id').value=id;
               addResourceForm.name.value=name;
               addResourceForm.logo.value=logo;
               document.getElementById('resource_version').value=version;
               if(addResourceForm.category_id) addResourceForm.category_id.value = cat || '';

               // بارگذاری لینک‌های موجود
               linkContainer.classList.remove('hidden');
               addLinkRow.classList.remove('hidden');
               linkContainer.innerHTML='';
               try{
                   const arr=JSON.parse(urls);
                   if(Array.isArray(arr) && arr.length){
                       const unique=[...new Set(arr)]; // حذف موارد تکراری
                       unique.forEach(u=>createRow('',u));
                   }else createRow();
               }catch(e){
                   createRow();
               }

               document.getElementById('modalTitle').textContent='ویرایش منبع';
               addResourceModal.classList.remove('hidden');
               return;
            }

            const addBtn=e.target.closest('#addResourceBtn');
            if(addBtn){
               document.getElementById('resource_id').value='';
               addResourceForm.reset();
               linkContainer.classList.remove('hidden');
               addLinkRow.classList.remove('hidden');
               document.getElementById('modalTitle').textContent='افزودن منبع جدید';
               resetRows();
               return;
            }

            // کاربران
            const delUser=e.target.closest('.del-user');
            if(delUser){
               if(!(await showConfirm('حذف شود؟','delete'))) return;
               const fd=new FormData();fd.append('action','delete');fd.append('id',delUser.dataset.id);
               const resp=await fetch('api/user.php',{method:'POST',body:fd});
               const d=await resp.json();
               return d.success?location.reload():showAlert(d.message||'خطا');
            }

            const banUser=e.target.closest('.ban-user');
            if(banUser){
               const fd=new FormData();fd.append('action','toggleBan');fd.append('id',banUser.dataset.id);
               const d=await (await fetch('api/user.php',{method:'POST',body:fd})).json();
               return d.success?location.reload():showAlert(d.message||'خطا');
            }

            const editUser=e.target.closest('.edit-user');
            if(editUser){
               document.getElementById('user_id').value=editUser.dataset.id;
               document.getElementById('user_name').value=editUser.dataset.name;
               document.getElementById('user_email').value=editUser.dataset.email;
               // اطمینان از اینکه مودال داخل تب پنهان نیست
               document.body.appendChild(userModal);
               userModal.classList.remove('hidden');
               return;
            }
            // حذف درخواست
            const delReq=e.target.closest('.del-req');
            if(delReq){
               if(!(await showConfirm('حذف شود؟','delete'))) return;
               const fd=new FormData();fd.append('action','delete');fd.append('id',delReq.dataset.id);
               const d=await (await fetch('api/request.php',{method:'POST',body:fd})).json();
               return d.success?location.reload():showAlert('خطا','error');
            }
            // نمایش مودال درخواست
            const viewReq=e.target.closest('.view-req');
            if(viewReq){
               const msg=viewReq.dataset.msg;
               showAlert(msg,'info');
               return;
            }
        });

        // toggle between link/upload modes
        const uploadSection=document.getElementById('uploadSection');
        const resFiles=document.getElementById('resFiles');
        document.querySelectorAll('input[name="resMode"]').forEach(r=>{
            r.addEventListener('change',()=>{
                const mode=document.querySelector('input[name="resMode"]:checked')?.value||'link';
                if(mode==='upload'){
                    linkContainer.classList.add('hidden');
                    addLinkRow.classList.add('hidden');
                    uploadSection.classList.remove('hidden');
                    // disable link inputs to avoid required validation
                    linkContainer.querySelectorAll('input[name="titles[]"], input[name="urls[]"]').forEach(el=>{ el.disabled=true; el.removeAttribute('required'); });
                    if(resFiles) resFiles.disabled=false;
                }else{
                    uploadSection.classList.add('hidden');
                    linkContainer.classList.remove('hidden');
                    addLinkRow.classList.remove('hidden');
                    // enable link inputs
                    linkContainer.querySelectorAll('input[name="titles[]"], input[name="urls[]"]').forEach(el=>{ el.disabled=false; el.setAttribute('required','required'); });
                    if(resFiles) resFiles.disabled=true;
                }
            });
        });
        // initialize state
        (function(){
           const mode=document.querySelector('input[name="resMode"]:checked')?.value||'link';
           if(mode==='upload'){
                linkContainer.querySelectorAll('input[name="titles[]"], input[name="urls[]"]').forEach(el=>{ el.disabled=true; el.removeAttribute('required'); });
                if(resFiles) resFiles.disabled=false;
           }else{
                if(resFiles) resFiles.disabled=true;
           }
        })();

        // Category modal logic
        const catModal=document.getElementById('catModal');
        document.getElementById('catManage').addEventListener('click',()=>catModal.classList.remove('hidden'));
        catModal.addEventListener('click',e=>{if(e.target===catModal) catModal.classList.add('hidden');});
        document.getElementById('addCat').addEventListener('click',async()=>{
          const title=document.getElementById('newCat').value.trim();
          const msg=document.getElementById('catMsg');
          if(!title){msg.textContent='عنوان را وارد کنید';return;}
          const fd=new FormData();fd.append('action','add');fd.append('title',title);
          const imgInput=document.getElementById('catImg');
          if(imgInput.files.length) fd.append('image',imgInput.files[0]);
          const res=await fetch('api/snippet_category.php',{method:'POST',body:fd}).then(r=>r.json());
          if(res.success) location.reload(); else msg.textContent=res.msg||'خطا';
        });
        document.querySelectorAll('.del-cat').forEach(btn=>{
           btn.addEventListener('click',async()=>{
              if(!await showConfirm('حذف شود؟','delete')) return;
              const fd=new FormData();fd.append('action','delete');fd.append('id',btn.dataset.id);
              const res=await fetch('api/snippet_category.php',{method:'POST',body:fd}).then(r=>r.json());
              if(res.success) location.reload();
           });
        });

        // ویرایش دسته منابع
        document.querySelectorAll('.edit-rescat').forEach(btn=>{
            btn.addEventListener('click',async()=>{
               const id=btn.dataset.id;
               const current=btn.dataset.title||'';
               const title=prompt('عنوان جدید دسته',current);
               if(title===null) return; // cancel
               const t=title.trim();
               if(!t){showAlert('عنوان نمیتواند خالی باشد','error');return;}
               const fd=new FormData();fd.append('action','update');fd.append('id',id);fd.append('title',t);
               const res=await fetch('api/resource_category.php',{method:'POST',body:fd}).then(r=>r.json());
               if(res.success) location.reload(); else showAlert(res.msg||'خطا','error');
            });
        });

        // Snippet modal logic
        const snipModal=document.getElementById('snippetModal');
        document.getElementById('addSnippetBtn').addEventListener('click',()=>snipModal.classList.remove('hidden'));
        document.getElementById('snippetCancel').addEventListener('click',()=>snipModal.classList.add('hidden'));
        document.getElementById('snippetForm').addEventListener('submit',async e=>{
            e.preventDefault();
            const msg=document.getElementById('snipMsg');msg.textContent='درحال ارسال...';
            const fd=new FormData(e.target);fd.append('action','add');
            const r=await fetch('api/snippet.php',{method:'POST',body:fd});
            const d=await r.json();
            if(d.success){msg.textContent='ذخیره شد';setTimeout(()=>location.reload(),700);}else msg.textContent=d.message||'خطا';
        });

        // حذف اسنیپت (delegation)
        document.addEventListener('click',async e=>{
           const del=e.target.closest('.del-snip');
           if(!del) return;
           if(!await showConfirm('حذف شود؟','delete')) return;
           const fd=new FormData();fd.append('action','delete');fd.append('id',del.dataset.id);
           const r=await fetch('api/snippet.php',{method:'POST',body:fd});
           const d=await r.json();
           if(d.success) location.reload(); else alert('خطا');
        });

        // بستن مودال و ریست
        userCancel?.addEventListener('click',()=>userModal.classList.add('hidden'));

        // ذخیره ویرایش کاربر
        const editUserForm=document.getElementById('editUserForm');
        const userMsg=document.getElementById('userMsg');

        editUserForm?.addEventListener('submit',async e=>{
            e.preventDefault();
            userMsg.textContent='در حال ارسال...';
            const fd=new FormData(editUserForm);
            fd.append('action','update');
            const resp=await fetch('api/user.php',{method:'POST',body:fd});
            const d=await resp.json();
            if(d.success){
               userMsg.textContent='بروزرسانی انجام شد';
               setTimeout(()=>location.reload(),1000);
            }else{
               userMsg.textContent=d.message||'خطا';
            }
        });

        // settings save
        document.getElementById('saveSettings')?.addEventListener('click',async()=>{
           const btn=document.getElementById('saveSettings');
           const msg=document.getElementById('setMsg');
           btn.disabled=true;msg.textContent='درحال ذخیره...';
           const pairs=[['free_limit',document.getElementById('setFree').value],['merchant_id',document.getElementById('setMid').value]];
           for(const [k,v] of pairs){
              const fd=new FormData();fd.append('key',k);fd.append('value',v);
              const r=await fetch('api/setting.php',{method:'POST',body:fd});
              const d=await r.json();
              if(!d.success){msg.textContent='خطا';btn.disabled=false;return;}
           }
           msg.textContent='ذخیره شد';btn.disabled=false;
        });

        const planM=document.getElementById('planModal');
        document.addEventListener('click',async e=>{
           const setp=e.target.closest('.set-plan');
           if(setp){document.getElementById('plan_user_id').value=setp.dataset.id;planM.classList.remove('hidden');return;}
        });
        document.getElementById('planCancel').addEventListener('click',()=>planM.classList.add('hidden'));
        document.getElementById('planSave').addEventListener('click',async()=>{
            const uid=document.getElementById('plan_user_id').value;
            const pid=document.getElementById('plan_select').value;
            const fd=new FormData();fd.append('action','set');fd.append('user_id',uid);fd.append('plan_id',pid);
            const r=await fetch('api/user_plan.php',{method:'POST',body:fd});
            const d=await r.json();
            if(d.success){showAlert('پلن ثبت شد','success');planM.classList.add('hidden');}
            else showAlert('خطا','error');
        });

        // Resource Category modal logic
        const resCatModal=document.getElementById('resCatModal');
        document.getElementById('resCatManage').addEventListener('click',()=>resCatModal.classList.remove('hidden'));
        resCatModal.addEventListener('click',e=>{if(e.target===resCatModal) resCatModal.classList.add('hidden');});

        document.getElementById('addResCat').addEventListener('click',async()=>{
          const title=document.getElementById('newResCat').value.trim();
          const msg=document.getElementById('resCatMsg');
          if(!title){msg.textContent='عنوان را وارد کنید';return;}
          const fd=new FormData();fd.append('action','add');fd.append('title',title);
          const res=await fetch('api/resource_category.php',{method:'POST',body:fd}).then(r=>r.json());
          if(res.success) location.reload(); else msg.textContent=res.msg||'خطا';
        });

        document.querySelectorAll('.del-rescat').forEach(btn=>{
           btn.addEventListener('click',async()=>{
              if(!await showConfirm('حذف شود؟','delete')) return;
              const fd=new FormData();fd.append('action','delete');fd.append('id',btn.dataset.id);
              const res=await fetch('api/resource_category.php',{method:'POST',body:fd}).then(r=>r.json());
              if(res.success) location.reload(); else showAlert(res.msg||'خطا','error');
           });
        });

        // نمایش منابع داخل دسته انتخابی
        document.querySelectorAll('.show-rescat').forEach(btn=>{
            btn.addEventListener('click',()=>{
               const cid=parseInt(btn.dataset.id);
               const list=RESOURCES_DATA.filter(r=>parseInt(r.category_id)===cid);
               if(!list.length){showAlert('هیچ کتابخانهای در این دسته نیست','info');return;}
               let html='<ul class="text-right space-y-2">';
               list.forEach(r=>{
                   html+=`<li class="bg-gray-700 rounded px-3 py-2 text-sm">${r.name} <span class="text-gray-400 text-xs">(v${r.version})</span></li>`;
               });
               html+='</ul>';
               showAlert(html,'info');
            });
        });
    </script>
</body>
</html> 