<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if(!isset($_SESSION['user_id']) || (int)$_SESSION['user_id']!==1){
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'دسترسی غیر مجاز']);
    exit;
}
require_once __DIR__.'/db.php';
$action=$_POST['action']??'';
$id=(int)($_POST['id']??0);
if(!$id){echo json_encode(['success'=>false,'message'=>'شناسه نامعتبر']);exit;}
if($action==='delete'){
    // جلوگیری از حذف ادمین اصلی
    if($id===1){echo json_encode(['success'=>false,'message'=>'نمی‌توان کاربر ادمین را حذف کرد']);exit;}
    $stmt=$pdo->prepare('DELETE FROM users WHERE id=?');
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}
if($action==='update'){
    $name=trim($_POST['name']??'');
    $email=trim($_POST['email']??'');
    $password=$_POST['password']??'';
    if(!$name||!$email){echo json_encode(['success'=>false,'message'=>'نام و ایمیل الزامی است']);exit;}
    $sql='UPDATE users SET name=?, email=?';
    $params=[$name,$email];
    if(strlen($password)>0){
        $hash=password_hash($password,PASSWORD_BCRYPT);
        $sql.=', password=?';
        $params[]=$hash;
    }
    $sql.=' WHERE id=?';
    $params[]=$id;
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success'=>true]);
    exit;
}
if($action==='toggleBan'){
    $stmt=$pdo->prepare('UPDATE users SET banned=IF(banned=1,0,1) WHERE id=?');
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'عملیات نامشخص']); 