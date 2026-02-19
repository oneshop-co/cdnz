<?php
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/payment_helpers.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: payment_result.php?status=error&msg=' . urlencode('لطفاً وارد شوید.'));
    exit;
}

$authority = trim($_GET['Authority'] ?? '');
$status = $_GET['Status'] ?? '';
$plan = (int)($_GET['plan'] ?? 0);
$expired = isset($_GET['expired']) && $_GET['expired'] == '1';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)$_SESSION['user_id'];

if ($userId !== (int)$_SESSION['user_id'] && !$expired) {
    header('Location: payment_result.php?status=error&msg=' . urlencode('دسترسی غیرمجاز'));
    exit;
}

$rec = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
$rec->execute([$plan]);
$p = $rec->fetch();
if (!$p) {
    header('Location: payment_result.php?status=error&msg=' . urlencode('پلن نامعتبر'));
    exit;
}

if ($status !== 'OK') {
    header('Location: payment_result.php?status=cancel');
    exit;
}

$merchant = $pdo->query("SELECT value FROM settings WHERE `key` = 'merchant_id'")->fetchColumn();
if (!$merchant || trim($merchant) === '') {
    payment_log('verify', 'merchant_id not set in settings', []);
    header('Location: payment_result.php?status=error&msg=' . urlencode('تنظیمات درگاه انجام نشده است.'));
    exit;
}
$merchant = trim($merchant);

$verify = [
    'merchant_id' => $merchant,
    'amount' => (int)$p['price'],
    'authority' => $authority,
];
$ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
curl_setopt_array($ch, [
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($verify),
]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

if (isset($res['data']['code']) && (int)$res['data']['code'] === 100) {
    $expire = date('Y-m-d H:i:s', strtotime('+30 days'));
    $ins = $pdo->prepare('INSERT INTO user_subscriptions(user_id, plan_id, expires_at) VALUES(?, ?, ?)');
    $ins->execute([$userId, $plan, $expire]);

    if ($expired) {
        header('Location: payment_result.php?status=ok&renewed=1');
    } else {
        header('Location: payment_result.php?status=ok');
    }
    exit;
}

payment_log('verify', 'zarinpal verify failed', $res ?? []);
header('Location: payment_result.php?status=error&code=' . (int)($res['data']['code'] ?? 0));
exit;
