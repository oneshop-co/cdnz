<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

require_once __DIR__ . '/db.php';


$action = $_POST['action'] ?? '';

function rrmdir($dir){
    if(!is_dir($dir)) return;
    foreach(scandir($dir) as $item){
        if($item==='.'||$item==='..') continue;
        $path="$dir/$item";
        if(is_dir($path)) rrmdir($path); else unlink($path);
    }
    rmdir($dir);
}

if($action==='delete'){
    $id=(int)($_POST['id']??0);
    if(!$id){echo json_encode(['success'=>false,'message'=>'شناسه نامعتبر']);exit;}
    $stmt=$pdo->prepare('SELECT local_path FROM resources WHERE id=?');
    $stmt->execute([$id]);
    $row=$stmt->fetch();
    if(!$row){echo json_encode(['success'=>false,'message'=>'منبع یافت نشد']);exit;}
    $folder=dirname(__DIR__,1).'/'.$row['local_path'];
    rrmdir($folder);
    $pdo->prepare('DELETE FROM resources WHERE id=?')->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}

if($action==='update'){
    $id=(int)($_POST['id']??0);
    $name=trim($_POST['name']??'');
    $version=trim($_POST['version']??'');
    $logo=trim($_POST['logo']??'');
    $catId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    if(!$id||!$name||!$version){echo json_encode(['success'=>false,'message'=>'اطلاعات ناقص']);exit;}
    // بررسی وجود فولدر منبع برای بروزرسانی فایل‌ها (در صورت ارسال لینک‌های جدید)
    $stmt=$pdo->prepare('SELECT local_path FROM resources WHERE id=?');
    $stmt->execute([$id]);
    $row=$stmt->fetch();
    if(!$row){echo json_encode(['success'=>false,'message'=>'منبع یافت نشد']);exit;}

    // استخراج لینک‌های جدید (در صورت وجود)
    $urls=[];
    $titles = isset($_POST['titles']) && is_array($_POST['titles']) ? $_POST['titles'] : [];
    if(isset($_POST['urls']) && is_array($_POST['urls'])){
        foreach($_POST['urls'] as $idx=>$u){
            $u=trim($u);
            if($u) $urls[]=$u;
        }
    }

    if($urls){
        $folder=dirname(__DIR__,1).'/'.$row['local_path'];
        // حذف فایل‌های قدیمی و پوشه
        rrmdir($folder);
        mkdir($folder,0755,true);

        $savedFiles=[];
        $labelMap=[];
        foreach($urls as $u){
            $base=basename(parse_url($u,PHP_URL_PATH));
            if(!$base) continue;
            $target=$folder.'/'.$base;
            $ch=curl_init($u);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30]);
            $data=curl_exec($ch);
            $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($data!==false && $code===200){
               file_put_contents($target,$data);
               $savedFiles[]=$row['local_path'].'/'.$base;
               $labelMap[$base] = isset($titles[$u]) && trim($titles[$u])!=='' ? trim($titles[$u]) : $base;
            }
        }

        if(!$savedFiles){
            echo json_encode(['success'=>false,'message'=>'دانلود فایل‌های جدید موفق نبود']);exit;
        }

        // ensure file_labels column
        try{ $pdo->query("SELECT file_labels FROM resources LIMIT 1"); }
        catch(PDOException $e){ $pdo->exec("ALTER TABLE resources ADD COLUMN file_labels TEXT NULL"); }
        $pdo->prepare('UPDATE resources SET name=?,version=?,logo=?,original_url=?,file_labels=?,category_id=? WHERE id=?')
             ->execute([$name,$version,$logo,json_encode($urls,JSON_UNESCAPED_UNICODE), json_encode($labelMap,JSON_UNESCAPED_UNICODE), $catId,$id]);
    }else{
        // تنها متادیتا را بروزرسانی کن
        $pdo->prepare('UPDATE resources SET name=?, version=?, logo=?, category_id=? WHERE id=?')
             ->execute([$name,$version,$logo,$catId,$id]);
    }

    echo json_encode(['success'=>true]);
    exit;
}

if($action!=='add'){
    echo json_encode(['success'=>false,'message'=>'عملیات نامشخص']);
    exit;
}

$name      = trim($_POST['name'] ?? '');
$version   = trim($_POST['version'] ?? '');
$logo_url  = trim($_POST['logo'] ?? '');
$file_url  = trim($_POST['url']  ?? '');
if(!$file_url && isset($_POST['urls'][0])){ $file_url = trim($_POST['urls'][0]); }
// category (optional)
$catId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;

if (!$name || !$version) {
    echo json_encode(['success' => false, 'message' => 'نام و نسخه الزامی است.']);
    exit;
}

// مسیر ذخیره عمومی
$cdnRoot = dirname(__DIR__,1).'/cdn';
if(!is_dir($cdnRoot) && !mkdir($cdnRoot,0755,true)){
    echo json_encode(['success'=>false,'message'=>'عدم توانایی در ایجاد پوشه ذخیره‌سازی']);
    exit;
}

// ساخت پوشه خاص منبع بر اساس نام (slug)‏
$slug = preg_replace('/[^a-z0-9_-]+/i','-', strtolower($name));
if(!$slug) $slug = 'resource_'.time();
$resourceDir = $cdnRoot.'/'.$slug;
$counter=1;
while(is_dir($resourceDir)){
    $resourceDir = $cdnRoot.'/'.$slug.'-'.$counter;
    $counter++;
}
if(!mkdir($resourceDir,0755,true)){
    echo json_encode(['success'=>false,'message'=>'خطا در ایجاد پوشه منبع']);
    exit;
}

// تعیین لیست لینک‌ها یا فایل‌های آپلودی
$urls = [];
// نگاشت عنوان سفارشی هر فایل (کلید: نام فایل)
$labelMap = [];
if(isset($_POST['urls']) && is_array($_POST['urls'])){
    $titles = isset($_POST['titles']) && is_array($_POST['titles']) ? $_POST['titles'] : [];
    foreach($_POST['urls'] as $i=>$u){ $u=trim($u); if($u){ $urls[]=$u; $base=basename(parse_url($u,PHP_URL_PATH)); $labelMap[$base]= isset($titles[$i]) && trim($titles[$i])!=='' ? trim($titles[$i]) : $base; } }
}
if($file_url) $urls[]=$file_url;

$uploadedFiles=[];
if(!empty($_FILES['files']['name'][0])){
    for($i=0;$i<count($_FILES['files']['name']);$i++){
        if($_FILES['files']['error'][$i]!==UPLOAD_ERR_OK) continue;
        $origName=basename($_FILES['files']['name'][$i]);
        $uploadedFiles[]=['tmp'=>$_FILES['files']['tmp_name'][$i],'name'=>$origName];
    }
}
if(!$urls && !$uploadedFiles){ echo json_encode(['success'=>false,'message'=>'هیچ فایلی ارسال نشد']); exit; }

$savedFiles=[];
// دانلود از لینک‌ها
foreach($urls as $u){
    $base = basename(parse_url($u,PHP_URL_PATH));
    if(!$base) continue;
    $targetPath=$resourceDir.'/'.$base;
    $ch=curl_init($u);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30]);
    $data=curl_exec($ch);
    $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($data!==false && $code===200){ file_put_contents($targetPath,$data); $savedFiles[]='cdn/'.basename($resourceDir).'/'.$base; if(!isset($labelMap[$base])) $labelMap[$base]=$base; }
}
// جابجایی فایل‌های آپلودی
foreach($uploadedFiles as $f){
    $base = $f['name'];
    $targetPath=$resourceDir.'/'.$base;
    if(move_uploaded_file($f['tmp'],$targetPath)){
        $savedFiles[]='cdn/'.basename($resourceDir).'/'.$base;
        if(!isset($labelMap[$base])) $labelMap[$base]=$base;
    }
}

if(!$savedFiles){
    echo json_encode(['success'=>false,'message'=>'دانلود هیچ فایلی موفق نبود']);
    exit;
}

// مسیر محلی ذخیره شده (فولدر)
$relativePath = 'cdn/'.basename($resourceDir);

// ایجاد جدول resources در صورت عدم وجود
$pdo->exec("CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    version VARCHAR(50) NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    original_url TEXT NOT NULL,
    file_labels TEXT NULL,
    local_path VARCHAR(255) NOT NULL,
    category_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ensure column exists if table already created
try{ $pdo->query("SELECT file_labels FROM resources LIMIT 1"); }
catch(PDOException $e){ $pdo->exec("ALTER TABLE resources ADD COLUMN file_labels TEXT NULL"); }
$stmt=$pdo->prepare('INSERT INTO resources (name, version, logo, original_url, file_labels, local_path, category_id) VALUES (?,?,?,?,?,?,?)');
$stmt->execute([$name,$version,$logo_url,json_encode($urls?:array_column($uploadedFiles,'name'),JSON_UNESCAPED_UNICODE), json_encode($labelMap,JSON_UNESCAPED_UNICODE), $relativePath,$catId]);

echo json_encode(['success' => true]); 