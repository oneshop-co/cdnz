<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if(!isset($_SESSION['user_id'])){
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'ورود لازم است']);
  exit;
}
require_once __DIR__.'/db.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS requests (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 message TEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4");

$action=$_POST['action']??'';
if($action==='add'){
   $msg=trim($_POST['message']??'');
   if(!$msg){echo json_encode(['success'=>false,'message'=>'متن خالی است']);exit;}
   $stmt=$pdo->prepare('INSERT INTO requests(user_id,message) VALUES(?,?)');
   $stmt->execute([$_SESSION['user_id'],$msg]);
   echo json_encode(['success'=>true]);
   exit;
}

if($action==='delete'){
   // فقط ادمین (id=1) اجازه حذف دارد
   if((int)$_SESSION['user_id']!==1){http_response_code(403);echo json_encode(['success'=>false]);exit;}
   $id=(int)($_POST['id']??0);
   if(!$id){echo json_encode(['success'=>false]);exit;}
   $stmt=$pdo->prepare('DELETE FROM requests WHERE id=?');
   $stmt->execute([$id]);
   echo json_encode(['success'=>true]);exit;
}

echo json_encode(['success'=>false,'message'=>'عملیات نامعتبر']); 