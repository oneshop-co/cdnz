<?php
// Secure file serving with bandwidth control and token validation
$root = __DIR__.'/cdn';
$fileParam = $_GET['file'] ?? '';
$token = $_GET['t'] ?? '';
if(!$fileParam){ http_response_code(400); exit('Bad Request'); }
$real = realpath($root.'/'.$fileParam);
if(!$real || strncmp($real, realpath($root), strlen(realpath($root)))!==0){ http_response_code(404); exit('Not Found'); }
$size = filesize($real);

require_once __DIR__.'/api/db.php';

// ------------ Validate user token ------------
$userId = null;
if($token){
    $stmt = $pdo->prepare('SELECT id FROM users WHERE link_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $userId = $stmt->fetchColumn();
}
if(!$userId){ http_response_code(403); exit('Forbidden'); }

// ------------ Check user subscription status ------------
function checkUserAccess(PDO $pdo, $userId) {
    // Check if user has used free plan before
    $freeUsed = $pdo->prepare('SELECT id FROM free_plan_used WHERE user_id = ?');
    $freeUsed->execute([$userId]);
    
    if ($freeUsed->fetch()) {
        // User has used free plan before, check for active subscription
        $now = date('Y-m-d H:i:s');
        $activeSub = $pdo->prepare('SELECT s.expires_at, p.name FROM user_subscriptions s 
                                   JOIN plans p ON p.id = s.plan_id 
                                   WHERE s.user_id = ? AND s.expires_at > ? 
                                   ORDER BY s.expires_at DESC LIMIT 1');
        $activeSub->execute([$userId, $now]);
        $subscription = $activeSub->fetch();
        
        if (!$subscription) {
            // No active subscription, redirect to payment page
            header('Location: /subscription_expired.php?user_id=' . $userId);
            exit;
        }
        
        return [
            'status' => 'active',
            'plan_name' => $subscription['name'],
            'expires_at' => $subscription['expires_at']
        ];
    } else {
        // First time user, allow free access
        return ['status' => 'free_first_time'];
    }
}

// Check user access
$userAccess = checkUserAccess($pdo, $userId);

// If this is first time free usage, mark it
if ($userAccess['status'] === 'free_first_time') {
    $markFree = $pdo->prepare('INSERT IGNORE INTO free_plan_used (user_id) VALUES (?)');
    $markFree->execute([$userId]);
}

// ensure tables
$pdo->exec("CREATE TABLE IF NOT EXISTS user_bandwidth (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 file_path VARCHAR(255) NOT NULL,
 bytes BIGINT UNSIGNED NOT NULL,
 served_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_user_file (user_id, file_path)
) ENGINE=InnoDB CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS plans (
 id INT PRIMARY KEY,
 name VARCHAR(50),
 price INT,
 limit_bytes BIGINT UNSIGNED
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS user_subscriptions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 plan_id INT NOT NULL,
 expires_at DATETIME,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// seed or update plans
$plansSeed=[
  [1,'Free',0,1073741824],
  [2,'Standard',500000,10737418240],
  [3,'Pro',700000,16106127360],
  [4,'Business',1200000,32212254720]
];
foreach($plansSeed as $p){
  [$id,$name,$price,$bytes]=$p;
  $stmt=$pdo->prepare("REPLACE INTO plans(id,name,price,limit_bytes) VALUES(?,?,?,?)");
  $stmt->execute([$id,$name,$price,$bytes]);
}

// add file_path column if old table exists without it
$colChk=$pdo->query("SHOW COLUMNS FROM user_bandwidth LIKE 'file_path'");
if($colChk && $colChk->rowCount()==0){
    $pdo->exec("ALTER TABLE user_bandwidth ADD COLUMN file_path VARCHAR(255) NOT NULL AFTER user_id");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_file ON user_bandwidth(user_id,file_path)");
}

// ------------- Bandwidth calculations -------------
// ensure settings table for free_limit
$pdo->exec("CREATE TABLE IF NOT EXISTS settings(`key` VARCHAR(50) PRIMARY KEY, `value` TEXT NOT NULL)");
$setLimit = $pdo->query("SELECT value FROM settings WHERE `key`='free_limit'")->fetchColumn();
$defaultLimit = $setLimit ? intval($setLimit) : 1073741824; // 1 GiB default

// active subscription check
$now = date('Y-m-d H:i:s');
$sub = $pdo->prepare("SELECT p.limit_bytes, s.expires_at FROM user_subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.user_id=? AND s.expires_at>? ORDER BY s.expires_at DESC LIMIT 1");
$sub->execute([$userId, $now]);
$row = $sub->fetch(PDO::FETCH_ASSOC);

$limit = $row ? intval($row['limit_bytes']) : $defaultLimit;
$from = date('Y-m-d H:i:s', strtotime('-30 days')); // دوره ۳۰ روز قبل یا شروع ماه برای رایگان

$used = $pdo->prepare("SELECT COALESCE(SUM(bytes),0) FROM user_bandwidth WHERE user_id=? AND served_at>=?");
$used->execute([$userId, $from]);
$bytesUsed = intval($used->fetchColumn());

if($bytesUsed + $size > $limit){ http_response_code(402); exit('Bandwidth limit exceeded'); }

// log usage
$log=$pdo->prepare('INSERT INTO user_bandwidth(user_id,file_path,bytes) VALUES(?,?,?)');
$log->execute([$userId,'cdn/'.$fileParam,$size]);

header('Cache-Control: public, max-age=31536000');
header('Access-Control-Allow-Origin: *');
header('Content-Length: '.$size);
$ext = pathinfo($real, PATHINFO_EXTENSION);
$map=['css'=>'text/css','js'=>'application/javascript','json'=>'application/json'];
$mime = $map[$ext] ?? mime_content_type($real) ?: 'application/octet-stream';
header('Content-Type: '.$mime);
readfile($real); 