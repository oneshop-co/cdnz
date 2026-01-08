<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if(!isset($_SESSION['user_id']) || (int)$_SESSION['user_id']!==1){http_response_code(403);echo json_encode(['success'=>false]);exit;}
require_once __DIR__.'/db.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS plans(id INT PRIMARY KEY,name VARCHAR(50),price INT,limit_bytes BIGINT UNSIGNED)");
$pdo->exec("CREATE TABLE IF NOT EXISTS user_subscriptions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, plan_id INT NOT NULL, expires_at DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$action=$_POST['action']??'';
if($action!=='set'){echo json_encode(['success'=>false,'msg'=>'bad']);exit;}
$user=(int)($_POST['user_id']??0);
$plan=(int)($_POST['plan_id']??0);
if(!$user||!$plan){echo json_encode(['success'=>false,'msg'=>'bad']);exit;}
$planRow=$pdo->prepare('SELECT limit_bytes FROM plans WHERE id=?');
$planRow->execute([$plan]);

if($plan==1){ // free plan: simply remove subscriptions
    $pdo->prepare('DELETE FROM user_subscriptions WHERE user_id=?')->execute([$user]);
    echo json_encode(['success'=>true]);exit;
}

$pdo->prepare('DELETE FROM user_subscriptions WHERE user_id=? AND expires_at>NOW()')->execute([$user]);
$expire=date('Y-m-d H:i:s',strtotime('+30 days'));
$pdo->prepare('INSERT INTO user_subscriptions(user_id,plan_id,expires_at) VALUES(?,?,?)')->execute([$user,$plan,$expire]);
echo json_encode(['success'=>true]); 