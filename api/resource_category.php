<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/db.php';

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS resource_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    slug  VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add') {
    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        echo json_encode(['success' => false, 'msg' => 'عنوان الزامی است.']);
        exit;
    }
    $slug = preg_replace('/[^a-z0-9_-]+/i','-', strtolower($title));
    if ($slug === '') $slug = 'cat_'.time();

    // Check duplicate
    $stmt = $pdo->prepare('SELECT id FROM resource_categories WHERE slug = ?');
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'msg' => 'دسته‌ای با این نام وجود دارد.']);
        exit;
    }
    $pdo->prepare('INSERT INTO resource_categories(title, slug) VALUES (?,?)')->execute([$title, $slug]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {echo json_encode(['success' => false]);exit;}
    // Prevent deleting category if resources exist
    try {
        $pdo->query('SELECT category_id FROM resources LIMIT 1');
    }catch(PDOException $e){ /* column may not exist yet */ }
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM resources WHERE category_id = ?');
    $cnt->execute([$id]);
    if ($cnt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'msg' => 'ابتدا منابع مرتبط را حذف یا جابجا کنید.']);
        exit;
    }
    $pdo->prepare('DELETE FROM resource_categories WHERE id = ?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    if(!$id || $title==='') {echo json_encode(['success'=>false,'msg'=>'اطلاعات ناقص']);exit;}
    $slug = preg_replace('/[^a-z0-9_-]+/i','-', strtolower($title));
    if($slug==='') $slug = 'cat_'.time();
    // Check duplicate slug (exclude current)
    $stmt=$pdo->prepare('SELECT id FROM resource_categories WHERE slug=? AND id<>?');
    $stmt->execute([$slug,$id]);
    if($stmt->fetch()){
        echo json_encode(['success'=>false,'msg'=>'نام تکراری است.']);exit;
    }
    $pdo->prepare('UPDATE resource_categories SET title=?, slug=? WHERE id=?')->execute([$title,$slug,$id]);
    echo json_encode(['success'=>true]);
    exit;
}

// default list
$rows = $pdo->query('SELECT id,title,slug FROM resource_categories ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
