<?php
/**
 * Simple file-based rate limiting for auth endpoints.
 * Limits sensitive auth actions per IP (e.g. 10 attempts per 15 minutes).
 */

function auth_rate_limit_exceeded(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $dir = sys_get_temp_dir() . '/cdnz_rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700);
    }
    $file = $dir . '/' . md5($ip);
    $windowSeconds = 900; // 15 minutes
    $maxAttempts = 10;

    $now = time();
    $count = 0;
    $windowStart = $now;

    if (file_exists($file) && is_readable($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $count = (int)($lines[0] ?? 0);
        $windowStart = (int)($lines[1] ?? $now);
        if ($now - $windowStart > $windowSeconds) {
            $count = 0;
            $windowStart = $now;
        }
    } else {
        $windowStart = $now;
    }

    $count++;
    @file_put_contents($file, $count . "\n" . $windowStart, LOCK_EX);

    return $count > $maxAttempts;
}
