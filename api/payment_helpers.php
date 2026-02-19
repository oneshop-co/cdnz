<?php
/**
 * Shared helpers for payment flow (pay.php, verify.php).
 */
function payment_log(string $step, string $message, $context = []): void
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = date('Y-m-d H:i:s') . " [{$step}] {$message} " . json_encode($context) . "\n";
    @file_put_contents($dir . '/payment.log', $line, FILE_APPEND | LOCK_EX);
}
