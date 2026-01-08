<?php
require_once __DIR__ . '/api/db.php';

// دریافت دسته‌بندی‌ها
$categories = $pdo->query('SELECT * FROM snippet_categories ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$currentCat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

if ($currentCat) {
    $stmt = $pdo->prepare('SELECT * FROM snippets WHERE category_id = ? ORDER BY id DESC');
    $stmt->execute([$currentCat]);
    $snippets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $snippets = $pdo->query('SELECT * FROM snippets ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نمونه‌کدها | CDNz</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
<script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-900 text-white font-sans p-6">
<div class="max-w-6xl mx-auto">
<h1 class="title-header text-3xl font-bold text-center">نمونه‌کدهای پیشنهادی</h1>

<!-- ===== بخش دسته‌بندی‌ها ===== -->
<style>
  .cat-card{
    width: 112px; /* w-28 */
    height: 112px; /* h-28 */
    border-radius: 1rem; /* rounded-2xl */
    padding: 1rem; /* p-4 */
    margin-top: 1rem;
    display:flex;flex-direction:column;justify-content:space-between;align-items:center;
    background:linear-gradient(145deg,#252b64,#151942);
    box-shadow:4px 4px 12px rgba(0,0,0,0.6),-4px -4px 12px rgba(255,255,255,0.05);
    transition:transform 0.25s ease,opacity 0.25s ease;
  }
  .cat-card:hover{transform:scale(1.05);opacity:0.98;}
  .cat-card-active{
    background:linear-gradient(145deg,#5b3bff,#3e1acc);
  }
  .cat-icon{
    width:40px;height:40px;
  }
</style>
<div class="flex justify-start sm:justify-center gap-4 overflow-x-auto pb-4 mb-8 rtl:space-x-reverse pl-4 pr-4">
    <!-- کارت «همه» -->
    <a href="snippets.php" class="flex-shrink-0">
        <div class="cat-card <?php echo $currentCat===0 ? 'cat-card-active' : ''; ?>">
            <i data-feather="grid" class="cat-icon <?php echo $currentCat===0 ? 'text-white' : 'text-purple-300'; ?>"></i>
            <span class="text-sm font-semibold <?php echo $currentCat===0 ? 'text-white' : 'text-gray-300'; ?> truncate">همه</span>
        </div>
    </a>
    <?php foreach ($categories as $c): $active=$currentCat===$c['id']; ?>
    <a href="snippets.php?cat=<?=$c['id']?>" class="flex-shrink-0">
        <div class="cat-card <?php echo $active ? 'cat-card-active' : ''; ?>">
            <?php if(!empty($c['image'])): ?>
               <img src="<?=htmlspecialchars($c['image'])?>" alt="img" class="cat-icon rounded object-cover"/>
            <?php else: ?>
               <i data-feather="layers" class="cat-icon <?php echo $active ? 'text-white' : 'text-purple-300'; ?>"></i>
            <?php endif; ?>
            <span class="text-sm font-semibold <?php echo $active ? 'text-white' : 'text-gray-300'; ?> truncate"><?=htmlspecialchars($c['title'])?></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- ===== استایل کارت‌های اسنیپت ===== -->
<style>
  .snippet-card{position:relative;width:100%;padding-top:56%;border-radius:1rem;overflow:hidden;background:linear-gradient(145deg,#1a1f46,#0e1127);box-shadow:4px 4px 12px rgba(0,0,0,0.6),-4px -4px 12px rgba(255,255,255,0.05);}  .snippet-card:hover{transform:scale(1.03);}  .snippet-card .snippet-info{position:absolute;bottom:0;left:0;right:0;padding:1rem;background:linear-gradient(0deg,rgba(0,0,0,0.7) 0%,rgba(0,0,0,0.0) 100%);}  .snippet-card .snippet-title{font-size:1rem;font-weight:700;color:#fff;}  .snippet-card .snippet-desc{font-size:0.70rem;color:#9ca3af;margin-top:0.25rem;line-height:1.1rem;max-height:2.2rem;overflow:hidden;}  .snippet-card .snippet-btn{font-size:0.725rem;padding:0.35rem 0.75rem;border-radius:0.5rem;background:#3ba0ff;color:#fff;white-space:nowrap;transition:background 0.2s ease;}  .snippet-card .snippet-btn:hover{background:#2686df;}  .snippet-icon{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);opacity:0.08;width:120px;height:120px;pointer-events:none;}
</style>
<!-- ===== لیست اسنیپت‌ها ===== -->
<div class="grid grid-snippets gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
<?php foreach($snippets as $s): ?>
  <div class="snippet-card transition-transform duration-300" style="<?php if(!empty($s['image'])){echo 'background-image:url(\''.htmlspecialchars($s['image']).'\');background-size:cover;background-position:center;';} ?>">
     <i data-feather="code" class="snippet-icon"></i>
     <div class="snippet-info flex justify-between items-end">
        <div>
          <h2 class="snippet-title" title="<?=htmlspecialchars($s['title'])?>"><?=htmlspecialchars($s['title'])?></h2>
          <p class="snippet-desc"><?=htmlspecialchars(mb_strimwidth($s['description']??'',0,70,'...'))?></p>
        </div>
        <a href="snippet_view.php?id=<?=$s['id']?>" class="snippet-btn">مشاهده</a>
     </div>
  </div>
<?php endforeach; ?>
<?php if(empty($snippets)): ?><p class="text-center text-gray-500 col-span-full">هنوز نمونه‌کدی ثبت نشده است.</p><?php endif; ?>
</div>
</div>
<style>
  /* ---------- اضافات مدرن ---------- */
  body{background:#0d1124;}
  .title-header{display:block;width:100%;background:linear-gradient(90deg,#1f225d,#212548);border:1px solid rgba(255,255,255,0.08);padding:1rem 0;border-radius:1rem;box-shadow:0 4px 12px rgba(0,0,0,0.45);margin-bottom:2rem;}
  .grid-snippets{justify-items:center;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));}
  .cat-bar{backdrop-filter:blur(8px) saturate(120%);}
  .cat-card:hover{transform:translateY(-6px) scale(1.07);opacity:1;box-shadow:0 12px 24px rgba(0,0,0,0.4);}
  .snippet-card{transition:transform .4s cubic-bezier(.25,.75,.35,1),box-shadow .4s;cursor:pointer;}
  .snippet-card:hover{transform:rotateX(3deg) rotateY(-3deg) scale(1.05);box-shadow:0 20px 32px rgba(0,0,0,0.45);}
</style>
<script>feather.replace();</script>
</body>
</html>