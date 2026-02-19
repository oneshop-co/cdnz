<?php
/**
 * Run pending database migrations.
 * Usage: php run_migrations.php   (CLI) or open in browser once after deploy.
 * Migrations are in migrations/*.sql; applied versions stored in schema_version.
 */
require_once __DIR__ . '/api/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (
    version VARCHAR(64) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4");

$applied = $pdo->query("SELECT version FROM schema_version")->fetchAll(PDO::FETCH_COLUMN);
$dir = __DIR__ . '/migrations';
$files = glob($dir . '/*.sql');
sort($files);

foreach ($files as $path) {
    $version = basename($path, '.sql');
    if (in_array($version, $applied, true)) {
        continue;
    }
    $sql = file_get_contents($path);
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function ($s) {
            $s = preg_replace('/--.*$/m', '', $s);
            return strlen(trim($s)) > 0;
        }
    );
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $code = $e->getCode();
            $msg = $e->getMessage();
            if ($code == 1060 || strpos($msg, 'Duplicate column') !== false ||
                $code == 1061 || strpos($msg, 'Duplicate key') !== false) {
                continue;
            }
            throw $e;
        }
    }
    $pdo->prepare("INSERT INTO schema_version (version) VALUES (?)")->execute([$version]);
    $applied[] = $version;
}

if (php_sapi_name() === 'cli') {
    echo "Migrations OK. Applied: " . implode(', ', $applied) . "\n";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2 style="color:#0f0;text-align:center;margin-top:40vh">Migration completed ✔</h2>';
}
