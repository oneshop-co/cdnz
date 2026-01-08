<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if(!isset($_SESSION['user_id'])){ http_response_code(401); echo json_encode(['success'=>false]); exit; }
require_once __DIR__.'/db.php';

// ensure columns on users table
try{$pdo->query("SELECT notify5d FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN notify5d TINYINT(1) NOT NULL DEFAULT 1");}
try{$pdo->query("SELECT notify2d FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN notify2d TINYINT(1) NOT NULL DEFAULT 1");}
try{$pdo->query("SELECT notifyExpired FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN notifyExpired TINYINT(1) NOT NULL DEFAULT 1");}
try{$pdo->query("SELECT notifyUsage FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN notifyUsage TINYINT(1) NOT NULL DEFAULT 1");}
try{$pdo->query("SELECT notifChannel FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN notifChannel ENUM('email','telegram') NOT NULL DEFAULT 'email'");}

$uid=(int)$_SESSION['user_id'];
$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if($method==='GET'){
  $st=$pdo->prepare('SELECT notify5d,notify2d,notifyExpired,notifyUsage,notifChannel FROM users WHERE id=?');
  $st->execute([$uid]);
  echo json_encode(['success'=>true,'data'=>$st->fetch(PDO::FETCH_ASSOC)]);
  exit;
}

$data=[
  'notify5d' => isset($_POST['notify5d'])?(int)$_POST['notify5d']:null,
  'notify2d' => isset($_POST['notify2d'])?(int)$_POST['notify2d']:null,
  'notifyExpired' => isset($_POST['notifyExpired'])?(int)$_POST['notifyExpired']:null,
  'notifyUsage' => isset($_POST['notifyUsage'])?(int)$_POST['notifyUsage']:null,
  'notifChannel' => $_POST['notifChannel'] ?? null,
];
$set=[];$params=[];
foreach($data as $k=>$v){ if($v!==null){ $set[]="$k=?"; $params[]=$v; } }
if(!$set){ echo json_encode(['success'=>false,'message'=>'nothing to update']); exit; }
$params[]=$uid;
$sql='UPDATE users SET '.implode(',', $set).' WHERE id=?';
$pdo->prepare($sql)->execute($params);
echo json_encode(['success'=>true]);
?>


