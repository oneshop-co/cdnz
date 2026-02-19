<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/rate_limit.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/** CSRF: فقط برای عملیات‌های حساس از طریق POST */
function validate_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/** عملیات‌هایی که نیاز به CSRF دارند */
$csrfActions = ['login', 'register_request', 'request_reset', 'confirm_reset', 'confirm_register'];
/** عملیات‌هایی که محدودیت تعداد درخواست دارند */
$rateLimitActions = ['login', 'register_request', 'request_reset'];

if (in_array($action, $rateLimitActions, true) && auth_rate_limit_exceeded()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'تلاش بیش از حد. لطفاً ۱۵ دقیقه صبر کنید.']);
    die;
}

switch ($action) {
    case 'register':
        register($pdo);
        break;
    case 'login':
        if (!validate_csrf()) {
            echo json_encode(['success' => false, 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.']);
            break;
        }
        login($pdo);
        break;
    case 'logout':
        logout();
        break;
    case 'request_reset':
        if (!validate_csrf()) {
            echo json_encode(['success' => false, 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.']);
            break;
        }
        request_reset($pdo);
        break;
    case 'confirm_reset':
        if (!validate_csrf()) {
            echo json_encode(['success' => false, 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.']);
            break;
        }
        confirm_reset($pdo);
        break;
    case 'register_request':
        if (!validate_csrf()) {
            echo json_encode(['success' => false, 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.']);
            break;
        }
        register_request($pdo);
        break;
    case 'confirm_register':
        if (!validate_csrf()) {
            echo json_encode(['success' => false, 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.']);
            break;
        }
        confirm_register($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'عملیات نامشخص']);
}

die();

function register(PDO $pdo): void
{
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name || !$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'تمام فیلدها الزامی هستند.']);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'ایمیل نامعتبر است.']);
        return;
    }

    // ensure token column exists
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS link_token VARCHAR(64) UNIQUE NULL");

    // Check if user exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'کاربری با این ایمیل وجود دارد.']);
        return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, link_token) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, $hash, $token]);

    echo json_encode(['success' => true]);
}

function login(PDO $pdo): void
{
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'ایمیل و رمز عبور الزامی است.']);
        return;
    }

    // ensure optional columns/tables
    try{$pdo->query("SELECT login_notify FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN login_notify TINYINT(1) NOT NULL DEFAULT 0");}
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, ip VARCHAR(64) NULL, user_agent VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");

    $stmt = $pdo->prepare('SELECT id, password, login_notify, email, name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'اطلاعات ورود اشتباه است.']);
        return;
    }

    $_SESSION['user_id'] = $user['id'];

    // log session
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,250);
    $log = $pdo->prepare('INSERT INTO user_sessions(user_id, ip, user_agent) VALUES(?,?,?)');
    $log->execute([$user['id'],$ip,$ua]);

    // optional login email notification
    if(!empty($user['login_notify'])){
        $subject = 'ورود جدید به حساب شما | CDNz';
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style=\"font-family:Tahoma,'Segoe UI',sans-serif;background:#f5f5f5;margin:0;padding:0;\">\n    <table width='100%' cellpadding='0' cellspacing='0'><tr><td align='center'>\n      <table width='600' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:8px;overflow:hidden;margin:20px'>\n        <tr><td style='background:#4c1d95;background:linear-gradient(90deg,#6d28d9,#4c1d95);padding:20px 30px;color:#fff;'>\n            <h1 style='margin:0;font-size:20px'>CDNz</h1>\n        </td></tr>\n        <tr><td style='padding:24px 30px;color:#333;'>\n            <p style='margin:0 0 10px'>سلام <strong>".htmlspecialchars($user['name']??'کاربر')."</strong>,</p>\n            <p style='margin:0 0 10px'>یک ورود جدید به حساب شما ثبت شد.</p>\n            <p style='margin:0 0 6px'><strong>IP:</strong> ".htmlspecialchars($ip)."</p>\n            <p style='margin:0 0 6px'><strong>مرورگر:</strong> ".htmlspecialchars($ua)."</p>\n            <p style='margin:14px 0 0;font-size:12px;color:#666'>اگر شما نبودید، رمز خود را تغییر دهید.</p>\n        </td></tr>\n      </table>\n    </td></tr></table></body></html>";
        @sendMailSMTP($user['email'],$subject,$html);
    }
    echo json_encode(['success' => true]);
}

function logout(): void
{
    // باطل کردن کوکی نشست برای خروج امن
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
    }
    session_unset();
    session_destroy();
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['success' => true]);
    } else {
        header('Location: ../index.php');
    }
}

// -------------------- Reset Password helpers --------------------
function ensureResetColumns(PDO $pdo){
  try{$pdo->query("SELECT reset_code FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN reset_code VARCHAR(6) NULL");}
  try{$pdo->query("SELECT reset_expire FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN reset_expire DATETIME NULL");}
}

function request_reset(PDO $pdo): void {
    ensureResetColumns($pdo);
    $email=trim($_POST['email']??'');
    if(!$email){echo json_encode(['success'=>false,'message'=>'ایمیل الزامی است.']);return;}
    $stmt=$pdo->prepare('SELECT id,name FROM users WHERE email=?');
    $stmt->execute([$email]);
    $userRow=$stmt->fetch();
    if(!$userRow){echo json_encode(['success'=>false,'message'=>'کاربری با این ایمیل یافت نشد.']);return;}
    $uid=$userRow['id'];
    $uname=$userRow['name'];
    $code=str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
    $expire=date('Y-m-d H:i:s',strtotime('+15 minutes'));
    $upd=$pdo->prepare('UPDATE users SET reset_code=?, reset_expire=? WHERE id=?');
    $upd->execute([$code,$expire,$uid]);

    // send email (HTML template)
    $subject='درخواست بازنشانی رمز عبور | CDNz';
    $html="<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style=\"font-family:Tahoma,'Segoe UI',sans-serif;background:#f5f5f5;margin:0;padding:0;\">
    <table width='100%' cellpadding='0' cellspacing='0'><tr><td align='center'>
      <table width='600' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:8px;overflow:hidden;margin:20px'>
        <tr><td style='background:#4c1d95;background:linear-gradient(90deg,#6d28d9,#4c1d95);padding:20px 30px;color:#fff;'>
            <h1 style='margin:0;font-size:24px'>CDNz</h1>
            <p style='margin:4px 0 0;font-size:12px'>cdnz.ir سریع‌ترین و ساده‌ترین راه استفاده از کتابخانه‌های محبوب وب در ایران</p>
        </td></tr>
        <tr><td style='padding:30px 30px 40px;color:#333;text-align:center;'>
            <h2 style='font-size:20px;margin-top:0;margin-bottom:16px'>درخواست بازنشانی رمز عبور</h2>
            <p style='margin:0 0 12px'>سلام <strong>{$uname}</strong>,</p>
            <p style='margin:0 0 20px'>شما (<span dir='ltr'>{$email}</span>) درخواست بازنشانی رمز عبور داده‌اید. کد تأیید شش‌رقمی شما:</p>
            <div style='font-size:32px;font-weight:bold;letter-spacing:4px;color:#4c1d95;margin:20px 0;text-align:center'>$code</div>
            <p style='font-size:14px;margin-top:20px'>این کد به مدت ۱۵ دقیقه معتبر است. اگر شما این درخواست را ارسال نکرده‌اید، لطفاً این ایمیل را نادیده بگیرید.</p>
            <p style='font-size:12px;color:#888;margin-top:30px'>© " . date('Y') . " CDNz</p>
        </td></tr>
      </table>
    </td></tr></table></body></html>";
    $headers="MIME-Version: 1.0\r\n".
             "Content-Type: text/html; charset=UTF-8\r\n".
             "From: CDNz <info@cdnz.ir>\r\n";
    sendMailSMTP($email,$subject,$html);

    echo json_encode(['success'=>true]);
}

function confirm_reset(PDO $pdo): void {
    ensureResetColumns($pdo);
    $email=trim($_POST['email']??'');
    $code=trim($_POST['code']??'');
    $password=$_POST['password']??'';
    if(!$email||!$code||!$password){echo json_encode(['success'=>false,'message'=>'اطلاعات ناقص']);return;}
    $stmt=$pdo->prepare('SELECT id, reset_code, reset_expire FROM users WHERE email=?');
    $stmt->execute([$email]);
    $row=$stmt->fetch();
    if(!$row || !$row['reset_code'] || $row['reset_code']!==$code){
        echo json_encode(['success'=>false,'message'=>'کد نامعتبر است']);return;}
    if(strtotime($row['reset_expire'])<time()){
        echo json_encode(['success'=>false,'message'=>'کد منقضی شده']);return;}
    $hash=password_hash($password,PASSWORD_BCRYPT);
    $upd=$pdo->prepare('UPDATE users SET password=?, reset_code=NULL, reset_expire=NULL WHERE id=?');
    $upd->execute([$hash,$row['id']]);
    echo json_encode(['success'=>true]);
}

// -------------------- Email Verification helpers --------------------
function ensureVerifyColumns(PDO $pdo){
  try{$pdo->query("SELECT verify_code FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN verify_code VARCHAR(6) NULL");}
  try{$pdo->query("SELECT verify_expire FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN verify_expire DATETIME NULL");}
  try{$pdo->query("SELECT verified FROM users LIMIT 1");}catch(PDOException $e){$pdo->exec("ALTER TABLE users ADD COLUMN verified TINYINT(1) NOT NULL DEFAULT 0");}
}

function register_request(PDO $pdo): void {
    ensureVerifyColumns($pdo);
    $name=trim($_POST['name']??'');
    $email=trim($_POST['email']??'');
    $password=$_POST['password']??'';
    if(!$name||!$email||!$password){echo json_encode(['success'=>false,'message'=>'تمام فیلدها الزامی هستند.']);return;}
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){echo json_encode(['success'=>false,'message'=>'ایمیل نامعتبر است.']);return;}
    $code=str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
    $expire=date('Y-m-d H:i:s',strtotime('+15 minutes'));
    $hash=password_hash($password,PASSWORD_BCRYPT);
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS link_token VARCHAR(64) UNIQUE NULL");
    $stmt=$pdo->prepare('SELECT id, verified FROM users WHERE email=?');
    $stmt->execute([$email]);
    $row=$stmt->fetch();
    if($row){
        if($row['verified']){echo json_encode(['success'=>false,'message'=>'کاربری با این ایمیل وجود دارد.']);return;}
        $upd=$pdo->prepare('UPDATE users SET name=?, password=?, verify_code=?, verify_expire=?, verified=0 WHERE id=?');
        $upd->execute([$name,$hash,$code,$expire,$row['id']]);
    }else{
        $token=bin2hex(random_bytes(16));
        $ins=$pdo->prepare('INSERT INTO users (name,email,password,link_token,verify_code,verify_expire,verified) VALUES (?,?,?,?,?,?,0)');
        $ins->execute([$name,$email,$hash,$token,$code,$expire]);
    }
    $subject='کد تایید ثبت نام | CDNz';
    $html="<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style=\"font-family:Tahoma,'Segoe UI',sans-serif;background:#f5f5f5;margin:0;padding:0;\">\n    <table width='100%' cellpadding='0' cellspacing='0'><tr><td align='center'>\n      <table width='600' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:8px;overflow:hidden;margin:20px'>\n        <tr><td style='background:#4c1d95;background:linear-gradient(90deg,#6d28d9,#4c1d95);padding:20px 30px;color:#fff;'>\n            <h1 style='margin:0;font-size:24px'>CDNz</h1>\n            <p style='margin:4px 0 0;font-size:12px'>cdnz.ir سریع‌ترین و ساده‌ترین راه استفاده از کتابخانه‌های محبوب وب در ایران</p>\n        </td></tr>\n        <tr><td style='padding:30px 30px 40px;color:#333;text-align:center;'>\n            <h2 style='font-size:20px;margin-top:0;margin-bottom:16px'>کد تایید ثبت نام</h2>\n            <p style='margin:0 0 12px'>سلام <strong>{$name}</strong>,</p>\n            <p style='margin:0 0 20px'>کد تایید شما:</p>\n            <div style='font-size:32px;font-weight:bold;letter-spacing:4px;color:#4c1d95;margin:20px 0;text-align:center'>{$code}</div>\n            <p style='font-size:14px;margin-top:20px'>این کد به مدت ۱۵ دقیقه معتبر است.</p>\n            <p style='font-size:12px;color:#888;margin-top:30px'>© ".date('Y')." CDNz</p>\n        </td></tr>\n      </table>\n    </td></tr></table></body></html>";
    $headers="MIME-Version: 1.0\r\n".
             "Content-Type: text/html; charset=UTF-8\r\n".
             "From: CDNz <info@cdnz.ir>\r\n";
    sendMailSMTP($email,$subject,$html);
    echo json_encode(['success'=>true]);
}

function confirm_register(PDO $pdo): void {
    ensureVerifyColumns($pdo);
    $email=trim($_POST['email']??'');
    $code=trim($_POST['code']??'');
    if(!$email||!$code){echo json_encode(['success'=>false,'message'=>'اطلاعات ناقص']);return;}
    $stmt=$pdo->prepare('SELECT id, verify_code, verify_expire, verified FROM users WHERE email=?');
    $stmt->execute([$email]);
    $row=$stmt->fetch();
    if(!$row||$row['verified']){echo json_encode(['success'=>false,'message'=>'کاربر یافت نشد یا قبلاً تایید شده']);return;}
    if($row['verify_code']!==$code){echo json_encode(['success'=>false,'message'=>'کد نادرست']);return;}
    if(strtotime($row['verify_expire'])<time()){echo json_encode(['success'=>false,'message'=>'کد منقضی شده']);return;}
    $upd=$pdo->prepare('UPDATE users SET verify_code=NULL, verify_expire=NULL, verified=1 WHERE id=?');
    $upd->execute([$row['id']]);
    $_SESSION['user_id']=$row['id'];
    echo json_encode(['success'=>true]);
}
?> 