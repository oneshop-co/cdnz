<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// اطمینان از بارگذاری اتولودر کامپوزر
require_once __DIR__.'/../vendor/autoload.php';

/**
 * ارسال ایمیل با استفاده از SMTP.
 * مقادیر پیکربندی از متغیرهای محیطی (.env) خوانده می‌شوند.
 */
function sendMailSMTP(string $to, string $subject, string $html): bool
{
    $mail = new PHPMailer(true);
    try {
        // تنظیمات سرور
        $mail->isSMTP();
        $mail->CharSet   = 'UTF-8';
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'localhost';
        $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? '';
        $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = ($mail->Port === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

        // فرستنده و گیرنده
        $from = $_ENV['SMTP_FROM'] ?? $mail->Username;
        $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'CDNz';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);

        // محتوا
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;

        return $mail->send();
    } catch (Exception $e) {
        error_log('Mail Error: ' . $e->getMessage());
        return false;
    }
}
