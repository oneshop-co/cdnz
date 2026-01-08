# CDNz Landing Page & Auth (PHP + MySQL)

این مخزن شامل یک لندینگ پیج تک‌صفحه‌ای (SPA) برای سرویس **CDNz** به همراه سیستم احراز هویت پایه (ورود / ثبت‌نام) است.

## پیش‌نیازها

1. **PHP ≥ 7.4** (با اکستنشن PDO)
2. **MySQL ≥ 5.7**
3. وب‌سرور (Apache/Nginx) یا built-in PHP server برای توسعه

## نصب و راه‌اندازی

```bash
# کلون یا کپی سورس
cd your-folder

# اجرای سرور توسعه (اختیاری)
php -S localhost:8000
```

سپس در مرورگر به `http://localhost:8000` بروید.

### ایجاد دیتابیس

```sql
CREATE DATABASE CDNz CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE CDNz;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

در فایل `api/db.php` اطلاعات اتصال (`$host`, `$db`, `$user`, `$pass`) را مطابق محیط خود تنظیم کنید.

## ساختار پروژه

```
CDNz/
├── api/
│   ├── db.php          # اتصال به پایگاه داده
│   └── auth.php        # API احراز هویت (login/register/logout)
├── js/
│   └── app.js          # منطق فرانت (jQuery)
├── index.php           # لندینگ پیج + SPA
└── README.md           # این فایل
```

## یادداشت‌ها

- **TailwindCSS** و **jQuery** به‌صورت CDN بارگذاری می‌شوند و نیازی به Node/NPM نیست.
- طراحی از تصاویر الهام گرفته شده و با تم تاریک / گرادینت آماده شده است.
- برای ساده بودن، عملیات احراز هویت به صورت ایجکسی با `auth.php` انجام می‌شود و از **Sessions** برای نگهداری وضعیت کاربر استفاده شده است.
- پس از ورود موفق، دکمه‌های ناوبری به حالت «داشبورد / خروج» تغییر می‌کنند.

## لایسنس
"MIT" 