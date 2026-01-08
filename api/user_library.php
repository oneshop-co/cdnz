<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if(!isset($_SESSION['user_id'])){
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'ابتدا وارد شوید']);
    exit;
}
require_once __DIR__.'/db.php';
$action=$_POST['action']??'';
$user_id=$_SESSION['user_id'];

// ایجاد جدول اگر وجود نداشت
$pdo->exec("CREATE TABLE IF NOT EXISTS user_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    resource_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_file (user_id, file_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// حذف ایندکس قدیمی که فقط روی resource_id بود (در صورت وجود) تا بتوان چند فایل از یک منبع اضافه کرد
try{ $pdo->exec("ALTER TABLE user_resources DROP INDEX uniq_user_res"); }catch(PDOException $e){}

// اطمینان از وجود ایندکس یکتا روی (user_id, file_path)
try{ $pdo->exec("ALTER TABLE user_resources ADD UNIQUE KEY uniq_user_file (user_id, file_path)"); }catch(PDOException $e){}

if($action==='add'){
    $resource_id=(int)($_POST['resource_id']??0);
    $file_path=trim($_POST['file_path']??'');
    if(!$resource_id||!$file_path){ echo json_encode(['success'=>false,'message'=>'داده نامعتبر']); exit; }
    // بررسی وجود فایل تکراری برای کاربر
    $dup=$pdo->prepare('SELECT id FROM user_resources WHERE user_id=? AND file_path=?');
    $dup->execute([$user_id,$file_path]);
    if($dup->fetch()){ echo json_encode(['success'=>false,'message'=>'قبلاً به لیست شما افزوده شده است']); exit; }

    // بررسی وجود منبع
    $chk=$pdo->prepare('SELECT id FROM resources WHERE id=?');
    $chk->execute([$resource_id]);
    if(!$chk->fetch()){ echo json_encode(['success'=>false,'message'=>'منبع یافت نشد']); exit; }

    try{
        $stmt=$pdo->prepare('INSERT INTO user_resources (user_id, resource_id, file_path) VALUES (?,?,?)');
        $stmt->execute([$user_id, $resource_id, $file_path]);
    }catch(PDOException $e){
        if($e->getCode()==23000){ echo json_encode(['success'=>false,'message'=>'قبلاً به لیست شما افزوده شده است']); exit; }
        throw $e;
    }
    echo json_encode(['success'=>true]);
    exit;
}

if($action==='delete'){
    $id=(int)($_POST['id']??0);
    if(!$id){echo json_encode(['success'=>false,'message'=>'شناسه نامعتبر']);exit;}
    $stmt=$pdo->prepare('DELETE FROM user_resources WHERE id=? AND user_id=?');
    $stmt->execute([$id,$user_id]);
    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'عملیات نامشخص']);
?> 