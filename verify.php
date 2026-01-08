<?php
require_once __DIR__.'/api/db.php';
session_start();
if(!isset($_SESSION['user_id'])) die('Login');

$authority=$_GET['Authority']??'';
$status=$_GET['Status']??'';
$plan=(int)($_GET['plan']??0);
$expired = isset($_GET['expired']) && $_GET['expired'] == '1';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];

// Verify user access
if ($userId !== $_SESSION['user_id']) {
    // Check if this is an expired subscription renewal
    if (!$expired) {
        die('Unauthorized access');
    }
}

$pdo->exec("CREATE TABLE IF NOT EXISTS plans(id INT PRIMARY KEY,name VARCHAR(50),price INT,limit_bytes BIGINT UNSIGNED)");
$pdo->exec("CREATE TABLE IF NOT EXISTS user_subscriptions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, plan_id INT NOT NULL, expires_at DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$rec=$pdo->prepare('SELECT * FROM plans WHERE id=?');
$rec->execute([$plan]);
$p=$rec->fetch();
if($p) die('plan');

if($status!=='OK') die('Payment canceled');

$mid=$pdo->query("SELECT value FROM settings WHERE `key`='merchant_id'")->fetchColumn();
$merchant=$mid?:'5aab4294-a0c4-40d9-a76e-d124c90aa5cf';
$verify=[
 'merchant_id'=>$merchant,
 'amount'=>$p['price'],
 'authority'=>$authority
];
$ch=curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_RETURNTRANSFER=>1,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($verify)]);
$res=json_decode(curl_exec($ch),true);

if(isset($res['data']['code']) && $res['data']['code']==100){
 $expire=date('Y-m-d H:i:s',strtotime('+30 days'));
 
 // Insert new subscription
 $ins=$pdo->prepare('INSERT INTO user_subscriptions(user_id,plan_id,expires_at) VALUES(?,?,?)');
 $ins->execute([$userId,$plan,$expire]);
 
 // If this was a renewal, redirect to dashboard, otherwise show success message
 if ($expired) {
     header('Location: dashboard.php?renewed=1');
     exit;
 } else {
     echo 'پرداخت موفق و پلن فعال شد';
 }
} else {
 echo 'خطا در تایید پرداخت';
} 