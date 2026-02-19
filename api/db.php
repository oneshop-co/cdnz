<?php
// ... new file ...
// Load environment variables from .env if present
$envPath = dirname(__DIR__,1).'/.env';
if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue; // skip comments and empty lines
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, null);
        if ($key !== null && $value !== null && !isset($_ENV[$key])) {
            $_ENV[$key] = trim($value);
        }
    }
}

$host    = $_ENV['DB_HOST']    ?? 'localhost';
$db      = $_ENV['DB_NAME']    ?? 'database';
$user    = $_ENV['DB_USER']    ?? 'user';
$pass    = $_ENV['DB_PASS']    ?? 'password';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Schema: run php run_migrations.php once after deploy.
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در اتصال به دیتابیس']);
    exit;
}
?>