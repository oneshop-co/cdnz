<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if(!isset($_SESSION['user_id'])){ http_response_code(401); echo json_encode(['success'=>false,'message'=>'login']); exit; }
require_once __DIR__.'/db.php';

$userId=(int)$_SESSION['user_id'];
$action=$_POST['action'] ?? $_GET['action'] ?? 'get';

// ensure column exists
$chk=$pdo->query("SHOW COLUMNS FROM users LIKE 'link_token'");
if($chk && $chk->rowCount()==0){ $pdo->exec("ALTER TABLE users ADD COLUMN link_token VARCHAR(64) UNIQUE NULL"); }

if($action==='get'){
  $st=$pdo->prepare('SELECT link_token FROM users WHERE id=?');
  $st->execute([$userId]);
  echo json_encode(['success'=>true,'token'=>$st->fetchColumn()?:null]);
  exit;
}

if($action==='rotate' || $action==='create'){
  $token=bin2hex(random_bytes(16));
  $up=$pdo->prepare('UPDATE users SET link_token=? WHERE id=?');
  $up->execute([$token,$userId]);
  echo json_encode(['success'=>true,'token'=>$token]);
  exit;
}

if($action==='revoke'){
  $up=$pdo->prepare('UPDATE users SET link_token=NULL WHERE id=?');
  $up->execute([$userId]);
  echo json_encode(['success'=>true,'token'=>null]);
  exit;
}

echo json_encode(['success'=>false,'message'=>'bad action']);
?>


