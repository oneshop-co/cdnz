<?php
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/payment_helpers.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: payment_result.php?status=error&msg=' . urlencode('لطفاً وارد شوید.'));
    exit;
}

$plan = (int)($_GET['plan'] ?? 0);
$expired = isset($_GET['expired']) && $_GET['expired'] == '1';
$userId = (int)($_GET['user_id'] ?? $_SESSION['user_id']);

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

$amount = (int)$p['price'];
$callback = $expired
    ? 'https://cdnz.ir/verify.php?plan=' . $plan . '&expired=1&user_id=' . $userId
    : 'https://cdnz.ir/verify.php?plan=' . $plan;

$merchant = $pdo->query("SELECT value FROM settings WHERE `key` = 'merchant_id'")->fetchColumn();
if (!$merchant || trim($merchant) === '') {
    payment_log('pay', 'merchant_id not set in settings', []);
    header('Location: payment_result.php?status=error&msg=' . urlencode('تنظیمات درگاه انجام نشده است.'));
    exit;
}
$merchant = trim($merchant);

$data = [
    'merchant_id' => $merchant,
    'amount' => $amount,
    'description' => 'خرید پلن ' . $p['name'] . ($expired ? ' (تمدید اشتراک)' : ''),
    'callback_url' => $callback,
];
$ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
curl_setopt_array($ch, [
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($data),
]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

if (isset($res['data']['authority'])) {
    header('Location: https://www.zarinpal.com/pg/StartPay/' . $res['data']['authority']);
    exit;
}

payment_log('pay', 'request failed', $res);
header('Location: payment_result.php?status=error&code=' . (int)($res['data']['code'] ?? 0));
exit; 