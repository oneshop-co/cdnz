<?php
/**
 * Migration script to create/alter all tables one-time.
 * Run this once (https://yourdomain/migrate.php) then delete or secure the file.
 */
require_once __DIR__ . '/api/db.php';

try {
    // users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        link_token VARCHAR(64) UNIQUE NULL,
        banned TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB CHARSET=utf8mb4;");

    // resources
    $pdo->exec("CREATE TABLE IF NOT EXISTS resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        version VARCHAR(50) NOT NULL,
        logo VARCHAR(255),
        original_url TEXT NOT NULL,
        local_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB CHARSET=utf8mb4;");

    // user_resources
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        resource_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_file (user_id, file_path)
    ) ENGINE=InnoDB CHARSET=utf8mb4;");

    // user_bandwidth
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_bandwidth (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        bytes BIGINT UNSIGNED NOT NULL,
        served_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_file (user_id, file_path)
    ) ENGINE=InnoDB CHARSET=utf8mb4;");

    // plans
    $pdo->exec("CREATE TABLE IF NOT EXISTS plans (
        id INT PRIMARY KEY,
        name VARCHAR(50),
        price INT,
        limit_bytes BIGINT UNSIGNED
    ) ENGINE=InnoDB CHARSET=utf8mb4;");

    // seed default plans
    $pdo->exec("INSERT IGNORE INTO plans(id,name,price,limit_bytes) VALUES
      (1,'Free',0,1073741824),
      (2,'Standard',500000,10737418240),
      (3,'Pro',700000,16106127360),
      (4,'Business',1200000,32212254720);");

    // user_subscriptions
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        plan_id INT NOT NULL,
        expires_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB CHARSET=utf8mb4;");

    // settings key/value
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings(
        `key` VARCHAR(50) PRIMARY KEY,
        `value` TEXT NOT NULL
    ) ENGINE=InnoDB CHARSET=utf8mb4;");

    // requests
    $pdo->exec("CREATE TABLE IF NOT EXISTS requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB CHARSET=utf8mb4;");

    // snippets & categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS snippet_categories(
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS snippets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        language VARCHAR(50) DEFAULT 'text',
        code TEXT NOT NULL,
        description TEXT,
        tags VARCHAR(255),
        category_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB CHARSET=utf8mb4;");

    echo '<h2 style="color:#0f0;text-align:center;margin-top:40vh">Migration completed ✔</h2>';
} catch (PDOException $e) {
    echo '<pre style="color:red">'.$e->getMessage().'</pre>';
    http_response_code(500);
}