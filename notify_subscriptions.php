<?php
// Cron-friendly script to notify users about subscription expiry milestones (5 days, 2 days, expired)
// Usage: run via cron/scheduler: php notify_subscriptions.php

require_once __DIR__.'/api/db.php';
require_once __DIR__.'/api/mailer.php';

// Ensure notification columns exist on user_subscriptions
try{ $pdo->query("SHOW COLUMNS FROM user_subscriptions LIKE 'notified_5d'"); }
catch(PDOException $e){ /* table missing, create minimal */ }
try{ $chk=$pdo->query("SHOW COLUMNS FROM user_subscriptions LIKE 'notified_5d'"); if($chk && $chk->rowCount()==0){ $pdo->exec("ALTER TABLE user_subscriptions ADD COLUMN notified_5d TINYINT(1) NOT NULL DEFAULT 0"); } }catch(PDOException $e){}
try{ $chk=$pdo->query("SHOW COLUMNS FROM user_subscriptions LIKE 'notified_2d'"); if($chk && $chk->rowCount()==0){ $pdo->exec("ALTER TABLE user_subscriptions ADD COLUMN notified_2d TINYINT(1) NOT NULL DEFAULT 0"); } }catch(PDOException $e){}
try{ $chk=$pdo->query("SHOW COLUMNS FROM user_subscriptions LIKE 'notified_expired'"); if($chk && $chk->rowCount()==0){ $pdo->exec("ALTER TABLE user_subscriptions ADD COLUMN notified_expired TINYINT(1) NOT NULL DEFAULT 0"); } }catch(PDOException $e){}

$siteUrl = rtrim($_ENV['SITE_URL'] ?? 'https://cdnz.ir', '/');

function tpl_email(string $title, string $messageHtml, string $siteUrl, string $variant): string {
    switch($variant){
        case '5d':
            $gradStart = '#10b981'; $gradEnd = '#06b6d4'; $btnBg = '#0ea5e9'; break;
        case '2d':
            $gradStart = '#f59e0b'; $gradEnd = '#ef4444'; $btnBg = '#f59e0b'; break;
        case 'expired':
        default:
            $gradStart = '#ef4444'; $gradEnd = '#f43f5e'; $btnBg = '#ef4444'; break;
    }
    $btn = '<a href="'.$siteUrl.'/" style="display:inline-block;padding:10px 18px;background:'.$btnBg.';color:#ffffff;border-radius:8px;text-decoration:none">ورود به سایت</a>';
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style=\"font-family:Tahoma,'Segoe UI',sans-serif;background:#f5f5f5;margin:0;padding:0;\">"
         . "<table width='100%' cellpadding='0' cellspacing='0'><tr><td align='center'>"
         . "<table width='600' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:8px;overflow:hidden;margin:20px;box-shadow:0 5px 20px rgba(0,0,0,.06)'>"
         . "<tr><td style=\"background:linear-gradient(90deg,{$gradStart},{$gradEnd});padding:20px 30px;color:#ffffff;\"><h1 style='margin:0;font-size:20px'>CDNz</h1></td></tr>"
         . "<tr><td style='padding:24px 30px;color:#333333;text-align:center;'>"
         . "<h2 style='font-size:20px;margin:0 0 16px'>".htmlspecialchars($title,ENT_QUOTES,'UTF-8')."</h2>"
         . "<div style='font-size:14px;line-height:1.9;margin-bottom:16px'>".$messageHtml."</div>"
         . "<div style='margin-top:6px'>".$btn."</div>"
         . "<p style='font-size:12px;color:#666666;margin-top:20px'>اگر روی دکمه کار نکرد، این لینک را باز کنید: <span dir='ltr'>".htmlspecialchars($siteUrl.'/',ENT_QUOTES,'UTF-8')."</span></p>"
         . "</td></tr></table></td></tr></table></body></html>";
}

function send_notice(PDO $pdo, array $row, string $type, string $siteUrl): bool {
    $name = $row['name'] ?? 'کاربر';
    $email= $row['email'] ?? '';
    if(!$email) return false;
    if($type==='5d'){
        $title='۵ روز تا پایان اشتراک';
        $body = "<p>هی رفیق! ⏳ فقط ۵ روز دیگه از اشتراکت مونده. بد نیست همین الان تمدید کنی که سایت و پروژه‌هات بی وقفه بدرخشن 💻✨</p>";
    }elseif($type==='2d'){
        $title='۲ روز تا پایان اشتراک';
        $body = "<p>اوه اوه! 😅 فقط ۲ روز تا پایان اشتراکت باقی مونده. بیا همین الان تمدیدش کن تا یه وقت وسط کار غافلگیر نشی 🚀</p>";
    }else{ // expired
        $title='پایان اشتراک';
        $body = "<p>خب دوست من 💔 اشتراکت تموم شد. ولی نگران نباش! فقط کافیه دوباره تمدیدش کنی تا همه چیز برگرده به همون حالت قبلی و پروژه‌هات دوباره پرواز کنن 🌟</p>";
    }
    $html = tpl_email($title, $body, $siteUrl, $type);
    return sendMailSMTP($email, $title.' | CDNz', $html);
}

// 5 days remaining: date match to avoid time drifts
$q5 = $pdo->prepare("SELECT s.id, s.user_id, u.email, u.name, s.expires_at
                     FROM user_subscriptions s JOIN users u ON u.id=s.user_id
                     WHERE DATE(s.expires_at) = DATE(DATE_ADD(NOW(), INTERVAL 5 DAY)) AND s.expires_at>NOW() AND (s.notified_5d=0)");
$q5->execute();
foreach($q5 as $row){ if(send_notice($pdo,$row,'5d',$siteUrl)){ $upd=$pdo->prepare('UPDATE user_subscriptions SET notified_5d=1 WHERE id=?'); $upd->execute([$row['id']]); } }

// 2 days remaining
$q2 = $pdo->prepare("SELECT s.id, s.user_id, u.email, u.name, s.expires_at
                     FROM user_subscriptions s JOIN users u ON u.id=s.user_id
                     WHERE DATE(s.expires_at) = DATE(DATE_ADD(NOW(), INTERVAL 2 DAY)) AND s.expires_at>NOW() AND (s.notified_2d=0)");
$q2->execute();
foreach($q2 as $row){ if(send_notice($pdo,$row,'2d',$siteUrl)){ $upd=$pdo->prepare('UPDATE user_subscriptions SET notified_2d=1 WHERE id=?'); $upd->execute([$row['id']]); } }

// expired (once)
$qe = $pdo->prepare("SELECT s.id, s.user_id, u.email, u.name, s.expires_at
                     FROM user_subscriptions s JOIN users u ON u.id=s.user_id
                     WHERE s.expires_at<=NOW() AND (s.notified_expired=0)");
$qe->execute();
foreach($qe as $row){ if(send_notice($pdo,$row,'expired',$siteUrl)){ $upd=$pdo->prepare('UPDATE user_subscriptions SET notified_expired=1 WHERE id=?'); $upd->execute([$row['id']]); } }

echo "OK\n";

