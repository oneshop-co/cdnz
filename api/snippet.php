<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

// create table if not exists
// ensure tables/columns
$pdo->exec("CREATE TABLE IF NOT EXISTS snippet_categories(
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ensure column description exists for older table
try{$pdo->query("SELECT description FROM snippets LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE snippets ADD COLUMN description TEXT NULL");}
// ensure category_id column
try{$pdo->query("SELECT category_id FROM snippets LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE snippets ADD COLUMN category_id INT NULL");}
// ensure image column
try{$pdo->query("SELECT image FROM snippets LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE snippets ADD COLUMN image VARCHAR(255) DEFAULT NULL");}

$pdo->exec("CREATE TABLE IF NOT EXISTS snippets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  language VARCHAR(50) DEFAULT 'text',
  code TEXT NOT NULL,
  description TEXT DEFAULT NULL,
  tags VARCHAR(255) DEFAULT NULL,
  category_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add') {
    $title = trim($_POST['title'] ?? '');
    $lang  = trim($_POST['language'] ?? 'text');
    $code  = $_POST['code'] ?? '';
    $desc  = trim($_POST['description'] ?? '');
    $tags  = trim($_POST['tags'] ?? '');
    if (!$title || !$code) {
        echo json_encode(['success' => false, 'message' => 'عنوان و کد الزامی است']);
        exit;
    }
    $cat  = (int)($_POST['category_id'] ?? 0) ?: null;

    // پردازش تصویر در صورت وجود
    $imgPath = null;
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,$allowed)){
            echo json_encode(['success'=>false,'message'=>'فرمت تصویر نامعتبر است']);
            exit;
        }
        $dir = dirname(__DIR__).'/uploads/snippets';
        if(!is_dir($dir) && !mkdir($dir,0755,true)){
            echo json_encode(['success'=>false,'message'=>'خطا در ایجاد پوشه آپلود']);
            exit;
        }
        $fname = uniqid('snip_',true).'.'.$ext;
        $target = $dir.'/'.$fname;
        if(!move_uploaded_file($_FILES['image']['tmp_name'],$target)){
            echo json_encode(['success'=>false,'message'=>'آپلود تصویر ناموفق بود']);
            exit;
        }
        $imgPath = 'uploads/snippets/'.$fname;
    }

    $stmt = $pdo->prepare('INSERT INTO snippets(title, language, code, description, tags, category_id, image) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$title, $lang, $code, $desc, $tags, $cat, $imgPath]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {echo json_encode(['success'=>false]);exit;}
    $stmt=$pdo->prepare('DELETE FROM snippets WHERE id=?');
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}

// default: list all snippets
$rows = $pdo->query('SELECT id,title,language,code,created_at FROM snippets ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
