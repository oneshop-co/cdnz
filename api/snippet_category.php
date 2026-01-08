<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
// ensure table
$pdo->exec("CREATE TABLE IF NOT EXISTS snippet_categories(
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  image VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
// ensure image column for older installs
try{$pdo->query("SELECT image FROM snippet_categories LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE snippet_categories ADD COLUMN image VARCHAR(255) DEFAULT NULL");}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if($action==='add'){
  $title=trim($_POST['title'] ?? '');
  if(!$title){echo json_encode(['success'=>false,'msg'=>'empty']);exit;}
  $imgPath=null;
  if(!empty($_FILES['image']['name']) && $_FILES['image']['error']===UPLOAD_ERR_OK){
     $allowed=['jpg','jpeg','png','gif','webp'];
     $ext=strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
     if(!in_array($ext,$allowed)){
        echo json_encode(['success'=>false,'msg'=>'نوع تصویر نامعتبر']);exit;
     }
     $dir=dirname(__DIR__).'/uploads/cat';
     if(!is_dir($dir) && !mkdir($dir,0755,true)){
        echo json_encode(['success'=>false,'msg'=>'خطا در ایجاد پوشه']);exit;
     }
     $fname=uniqid('cat_',true).'.'.$ext;
     $target=$dir.'/'.$fname;
     if(!move_uploaded_file($_FILES['image']['tmp_name'],$target)){
        echo json_encode(['success'=>false,'msg'=>'آپلود ناموفق']);exit;
     }
     $imgPath='uploads/cat/'.$fname;
  }
  $stmt=$pdo->prepare('INSERT INTO snippet_categories(title,image) VALUES (?,?)');
  $stmt->execute([$title,$imgPath]);
  echo json_encode(['success'=>true]);exit;
}
if($action==='delete'){
  $id=(int)($_POST['id']??0);
  if(!$id){echo json_encode(['success'=>false]);exit;}
  $pdo->prepare('DELETE FROM snippet_categories WHERE id=?')->execute([$id]);
  echo json_encode(['success'=>true]);exit;
}
// list
$rows=$pdo->query('SELECT id,title,image FROM snippet_categories ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success'=>true,'data'=>$rows],JSON_UNESCAPED_UNICODE);