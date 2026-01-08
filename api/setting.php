<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if(!isset($_SESSION['user_id']) || (int)$_SESSION['user_id']!==1){http_response_code(403);echo json_encode(['success'=>false]);exit;}
require_once __DIR__.'/db.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS settings(`key` VARCHAR(50) PRIMARY KEY, `value` TEXT NOT NULL)");
$key=trim($_POST['key']??'');
$val=trim($_POST['value']??'');
if(!$key){echo json_encode(['success'=>false,'msg'=>'bad']);exit;}
$stmt=$pdo->prepare('REPLACE INTO settings(`key`,`value`) VALUES(?,?)');
$stmt->execute([$key,$val]);
echo json_encode(['success'=>true]); 