<?php
header('Content-Type: application/xml');
require_once __DIR__.'/api/db.php';
$urls=[
  ['loc'=>'https://cdnz.ir/','priority'=>'1.0'],
];
$resources=$pdo->query('SELECT DISTINCT local_path FROM resources')->fetchAll(PDO::FETCH_COLUMN);
foreach($resources as $p){
    $urls[]=['loc'=>'https://cdnz.ir/'.$p,'priority'=>'0.6'];
}
$xml="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml.="<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach($urls as $u){
    $xml.="  <url><loc>{$u['loc']}</loc><priority>{$u['priority']}</priority></url>\n";
}
$xml.="</urlset>";
echo $xml; 