<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../api/db.php';

$path=trim($_GET['file']??'');
if($path===''){ echo json_encode(['success'=>false,'message'=>'file required']); exit; }
$full=dirname(__DIR__).'/'.$path;
if(!is_file($full)){ http_response_code(404); echo json_encode(['success'=>false,'message'=>'not found']); exit; }

$data=file_get_contents($full);
$hash=base64_encode(hash('sha384',$data,true));
$algo='sha384';
$sri=$algo.'-'.$hash;
// Build simple tags
$ext=strtolower(pathinfo($full,PATHINFO_EXTENSION));
if($ext==='css'){
  $tag='<link rel="stylesheet" href="/'.$path.'" integrity="'.$sri.'" crossorigin="anonymous">';
}else{
  $tag='<script src="/'.$path.'" integrity="'.$sri.'" crossorigin="anonymous"></script>';
}
echo json_encode(['success'=>true,'sri'=>$sri,'tag'=>$tag]);
?>


