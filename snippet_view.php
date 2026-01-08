<?php
require_once __DIR__ . '/api/db.php';
$id = (int)($_GET['id'] ?? 0);
$snip = null;
if($id){
  $stmt = $pdo->prepare('SELECT * FROM snippets WHERE id=? LIMIT 1');
  $stmt->execute([$id]);
  $snip = $stmt->fetch(PDO::FETCH_ASSOC);
}
if(!$snip){
  http_response_code(404);
  echo '<h1 style="color:#fff;text-align:center;margin-top:40vh">404 | نمونه کد یافت نشد</h1>';exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($snip['title'])?> | نمونه کد</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
<script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-900 text-white font-sans p-6">
<div class="max-w-4xl mx-auto space-y-8">
 <div class="flex justify-between items-center">
   <a href="snippets.php" class="inline-flex items-center gap-1 text-purple-400 hover:text-purple-300 text-sm"><i data-feather="arrow-right" class="w-4 h-4"></i>بازگشت</a>
 </div>
 <div class="bg-gray-800/60 border border-gray-700 rounded-2xl shadow-2xl p-8 relative overflow-hidden ring-1 ring-white/10 backdrop-blur-md">
   <?php if(!empty($snip['image'])): ?>
     <img src="<?=htmlspecialchars($snip['image'])?>" alt="thumb" class="absolute inset-0 w-full h-full object-cover opacity-10 pointer-events-none">
   <?php endif; ?>
   <h1 class="text-3xl font-extrabold mb-4 relative z-10 flex items-center gap-2"><i data-feather="code" class="w-7 h-7 text-purple-400"></i><?=htmlspecialchars($snip['title'])?></h1>
   <?php if($snip['description']): ?>
   <p class="text-gray-300 mb-6 leading-relaxed relative z-10">
      <?=nl2br(htmlspecialchars($snip['description']))?>
   </p>
   <?php endif; ?>
   <div class="relative z-10">
     <button id="copyBtn" class="absolute left-4 top-4 px-4 py-1.5 bg-purple-600 hover:bg-purple-700 rounded-full text-xs flex items-center gap-1 shadow-lg">
        <i data-feather="copy" class="w-4 h-4"></i><span>کپی</span>
     </button>
     <pre class="rounded-xl overflow-x-auto bg-gray-900/80 border border-gray-700 p-6 text-sm"><code id="codeBlock" class="language-<?=htmlspecialchars($snip['language'])?> ltr:direction-ltr rtl:direction-ltr"><?=htmlspecialchars($snip['code'])?></code></pre>
   </div>
   <?php if($snip['tags']): ?>
   <p class="text-xs text-gray-400 mt-4 relative z-10">برچسب‌ها: <?=htmlspecialchars($snip['tags'])?></p>
   <?php endif; ?>
 </div>
</div>
<script>hljs.highlightAll(); feather.replace();
document.getElementById('copyBtn').addEventListener('click',async()=>{
  const txt=document.getElementById('codeBlock').textContent;
  await navigator.clipboard.writeText(txt);
  copyBtn.textContent='کپی شد';
  setTimeout(()=>copyBtn.innerHTML='<i data-feather="copy" class="w-4 h-4"></i>کپی',1000);
});
</script>
</body>
</html>