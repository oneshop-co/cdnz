# CDNz | Iranian CDN Landing and Service

Landing, authentication, user dashboard, and CDN serving with token and bandwidth control for web libraries.

## Prerequisites

- **PHP ≥ 7.4** (PDO, curl, json)
- **MySQL ≥ 5.7** (utf8mb4)
- Web server (Apache with mod_rewrite) or `php -S` for development

## Installation and Setup

### 1. Clone and Dependencies

```bash
git clone ...
cd cdnz
composer install
```

### 2. Environment Configuration

```bash
cp .env.example .env
# Edit .env and set real values for DB_* and SMTP_*
```

Important variables in `.env`:

| Variable | Description |
|--------|-------------|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL connection |
| `DB_CHARSET` | Usually `utf8mb4` |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` | Email sending (registration confirmation, password reset) |
| `SMTP_FROM`, `SMTP_FROM_NAME` | Email sender |

### 3. Database and Migrations

```sql
CREATE DATABASE cdnz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then run migrations once:

```bash
php run_migrations.php
```

Or in browser: `https://yourdomain/run_migrations.php` (once, then restrict/remove the file if desired).

### 4. Payment Gateway (Zarinpal)

- In the admin panel, save the Zarinpal **merchant_id** in **Settings**.
- Without this value, payment is disabled and the error "Gateway settings not configured" is displayed.

### 5. Running the Server (Development)

```bash
php -S localhost:8000
```

Browser: `http://localhost:8000`

## Project Structure

```
cdnz/
├── api/
│   ├── db.php              # DB connection and .env loading
│   ├── auth.php            # Login / Register / Logout / Password reset / Email verification
│   ├── mailer.php          # Email sending (SMTP)
│   ├── rate_limit.php      # Rate limiting for auth
│   ├── payment_helpers.php # Payment log
│   └── ...
├── migrations/             # Database migrations (versioned)
│   ├── 001_baseline.sql
│   └── 002_alter_add_missing_columns.sql
├── storage/
│   └── logs/               # Payment log etc. (web access blocked)
├── js/app.js
├── index.php               # Landing and login/register forms
├── dashboard.php          # User dashboard
├── pay.php                # Payment request (Zarinpal)
├── verify.php             # Payment verification callback
├── payment_result.php     # Payment result page (success / error / cancel)
├── serve.php              # Serve CDN file with token and traffic limit
├── run_migrations.php     # Run migrations
├── .env.example
└── README.md
```

## Payment Flow

1. User selects a plan from the dashboard or pricing → `pay.php?plan=...`
2. If `merchant_id` is configured, user is redirected to Zarinpal.
3. After payment, Zarinpal returns the user to `verify.php`.
4. If verification succeeds, redirect to `payment_result.php?status=ok`; otherwise to `payment_result.php?status=error` or `status=cancel`.
5. Gateway errors are logged in `storage/logs/payment.log`.

## Security

- The `.env` file and `storage/` directory are blocked from direct web access (`.htaccess`).
- **CSRF** and **Rate limiting** are used for login and registration forms.
- After logout, the session cookie is invalidated.

## UX and Auxiliary Pages

- **Pricing:** "Get Started" / "Choose Plan" buttons for logged-in users link to the dashboard or `pay.php?plan=...`, and for guests to the login/register modal.
- **Modals:** Close with **Escape**, return focus to the opener button, and use `role="dialog"` and `aria-hidden` for screen readers.
- **404:** `404.php` page for invalid URLs; set in `.htaccess` with `ErrorDocument 404` (adjust path if installed in a subdirectory).

## License

MIT
