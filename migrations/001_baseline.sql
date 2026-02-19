-- CDNz baseline schema (fresh install).
-- Run via: php run_migrations.php

CREATE TABLE IF NOT EXISTS schema_version (
    version VARCHAR(64) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    link_token VARCHAR(64) UNIQUE NULL,
    login_notify TINYINT(1) NOT NULL DEFAULT 0,
    reset_code VARCHAR(6) NULL,
    reset_expire DATETIME NULL,
    verify_code VARCHAR(6) NULL,
    verify_expire DATETIME NULL,
    verified TINYINT(1) NOT NULL DEFAULT 0,
    banned TINYINT(1) DEFAULT 0,
    notify5d TINYINT(1) NOT NULL DEFAULT 1,
    notify2d TINYINT(1) NOT NULL DEFAULT 1,
    notifyExpired TINYINT(1) NOT NULL DEFAULT 1,
    notifyUsage TINYINT(1) NOT NULL DEFAULT 1,
    notifChannel ENUM('email','telegram') NOT NULL DEFAULT 'email'
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS free_plan_used (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plans (
    id INT PRIMARY KEY,
    name VARCHAR(50),
    price INT,
    limit_bytes BIGINT UNSIGNED
) ENGINE=InnoDB CHARSET=utf8mb4;

INSERT IGNORE INTO plans(id,name,price,limit_bytes) VALUES
(1,'Free',0,1073741824),
(2,'Standard',500000,10737418240),
(3,'Pro',700000,16106127360),
(4,'Business',1200000,32212254720);

CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notified_5d TINYINT(1) NOT NULL DEFAULT 0,
    notified_2d TINYINT(1) NOT NULL DEFAULT 0,
    notified_expired TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_bandwidth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    bytes BIGINT UNSIGNED NOT NULL,
    served_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_file (user_id, file_path)
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resource_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    image VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    version VARCHAR(50) NOT NULL DEFAULT '1.0.0',
    logo VARCHAR(255) NULL,
    original_url TEXT NOT NULL,
    local_path VARCHAR(255) NOT NULL,
    file_labels TEXT NULL,
    category_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    resource_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_file (user_id, file_path)
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` TEXT NOT NULL
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS snippet_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    image VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS snippets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    language VARCHAR(50) DEFAULT 'text',
    code TEXT NOT NULL,
    description TEXT NULL,
    tags VARCHAR(255) NULL,
    category_id INT NULL,
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    amount INT NOT NULL,
    authority VARCHAR(100) NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4;
