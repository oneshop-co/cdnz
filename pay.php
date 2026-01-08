<?php
require_once __DIR__.'/api/db.php';
session_start();
if(!isset($_SESSION['user_id'])){die('login');}

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
$rec=$pdo->prepare('SELECT * FROM plans WHERE id=?');
$rec->execute([$plan]);
$p=$rec->fetch();
if(!$p){die('plan');}

$amount=$p['price']; // زرین پال مبلغ را به ریال می‌گیرد

// Set callback URL based on whether this is a renewal or new subscription
if ($expired) {
    $callback='https://cdnz.ir/verify.php?plan='.$plan.'&expired=1&user_id='.$userId;
} else {
    $callback='https://cdnz.ir/verify.php?plan='.$plan;
}

$mid=$pdo->query("SELECT value FROM settings WHERE `key`='merchant_id'")->fetchColumn();
$merchant=$mid?:'5aab4294-a0c4-40d9-a76e-d124c90aa5cf';
$data=[
 'merchant_id'=>$merchant,
 'amount'=>$amount,
 'description'=>'خرید پلن '.$p['name'].($expired ? ' (تمدید اشتراک)' : ''),
 'callback_url'=>$callback
];
$ch=curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_RETURNTRANSFER=>1,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($data)]);
$res=json_decode(curl_exec($ch),true);
if(isset($res['data']['authority'])){
 header('Location: https://www.zarinpal.com/pg/StartPay/'.$res['data']['authority']);
 exit;
}
print_r($res); 