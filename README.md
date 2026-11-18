# Student Blog

A PHP and MySQL community site for students. It combines editorial posts with student-focused opportunities, events, marketplace listings, and community features such as comments, reactions, bookmarks, notifications, and anonymous SafeSpeak reports.

## Features

- Browse and explore posts by category; search and read individual articles.
- Register and sign in as a user, maintain a profile, comment, react, bookmark posts, and manage notifications.
- Discover events and opportunities.
- Browse marketplace listings, view item details, and use the shopping cart. M-Pesa checkout is configured for mock mode by default; Daraja sandbox or live use requires credentials and a reachable callback URL.
- Admin area for managing posts, comments, events, opportunities, marketplace listings, and SafeSpeak reports.
- SafeSpeak allows anonymous reports across academic, bullying, safety, discrimination, and other categories.

## Technology

- PHP (procedural pages and shared PHP helpers)
- MySQL or MariaDB through PHP MySQLi
- Browser-side JavaScript and CSS; no Node build step is defined

## Repository layout

```text
user/
  public/       Public site pages and AJAX endpoints; configure this as the web root
  admin/        Admin sign-in/access requests and administrator tools
  super_admin/  Super-admin sign-in and platform oversight tools
  shared/       Database connection, shared page parts, helpers, CSRF and payment logic
  assets/       Front-end JavaScript and post images
  public/uploads/ Marketplace uploads
safespeak_setup.sql  Supplemental SafeSpeak tables and activity-log fields
```

## Requirements

- PHP with `mysqli` and `openssl` enabled (and `curl` if using the Daraja integration)
- MySQL or MariaDB
- Apache, Nginx, or PHP's built-in development server

## Local setup

1. Install PHP and MySQL/MariaDB, then create a database named `student_blog` with UTF-8 support.
2. Import or create the application's base schema and any required seed data. The repository does not include a complete database schema dump; `safespeak_setup.sql` only adds SafeSpeak-related tables and activity-log columns and assumes the base `users` and `activity_log` tables already exist.
3. Configure database connection values in `user/shared/db_connect.php`. The checked-in defaults target a local XAMPP-style MySQL instance (`localhost`, user `root`, blank password, database `student_blog`). Change these values for your environment; do not deploy with development credentials.
4. Point the web server document root at `user/public`. For a quick local preview from the repository root, run:

   ```sh
   php -S 127.0.0.1:8000 -t user/public
   ```

   Then open <http://127.0.0.1:8000>. The built-in server is for local development, not production hosting.
5. Ensure PHP can write to `user/public/uploads/marketplace` and that the directory is served as intended. Keep uploaded content within the public upload location only if it is meant to be publicly retrievable.
6. To enable anonymous report administration, apply `safespeak_setup.sql` after the base schema exists. Its sample report insert is optional and can be removed before applying.

## M-Pesa configuration

`user/shared/mpesa_config.php` selects `mock` mode by default. For Daraja integration, configure the consumer key and secret, shortcode, passkey, and a publicly reachable callback URL in that file, then select the intended mode. Never expose real credentials in a public repository. The callback cannot be reached by Daraja when it points at localhost.

## Important routes

Public pages are relative to the `user/public` document root:

| Area | Path |
| --- | --- |
| Home | `/` or `/index.php` |
| Explore posts | `/explore.php` |
| Events | `/events.php` |
| Opportunities | `/opportunities.php` |
| Marketplace | `/marketplace.php` |
| User sign-in / registration | `/login.php` and `/register.php` |

### Admin and super-admin access

The admin and super-admin directories are siblings of `public`, so their URLs are not available from a server whose document root is only `user/public`. In the local development setup, keep the public server running on port 8000 and start a second PHP server from the repository root with `user` as its document root:

```sh
php -S 127.0.0.1:8001 -t user
```

On Windows with XAMPP, use the bundled PHP executable if `php` is not on `PATH`:

```bat
C:\xampp\php\php.exe -S 127.0.0.1:8001 -t C:\MUREITHI\PROJECTX\student_blog\user
```

Keep that terminal open, then use:

| Portal | Local URL | Purpose |
| --- | --- | --- |
| Admin access | <http://127.0.0.1:8001/admin/register.php> | Admin sign-in and admin access requests |
| Super-admin sign-in | <http://127.0.0.1:8001/super_admin/login.php> | Super-admin sign-in |
| Public homepage on this server | <http://127.0.0.1:8001/public/> | Public site when using the `user` document root |

`admin/register.php` is intentionally the admin access page despite its filename. Use its sign-in tab to access an existing administrator account, or submit an access request. New requests are stored with `pending` status and cannot sign in until approved by a super admin. Accounts with the `super_admin` role must use the separate super-admin portal. Both portals authenticate against the `admins` table and only allow accounts whose status is `approved`.

There are no default admin credentials or repository-provided bootstrap script. A super-admin account must already exist in `admins` with role `super_admin` and status `approved`. On its first password sign-in, the super-admin portal requires enrollment in a TOTP authenticator app; follow the setup page, save the setup key in the app, and enter its current six-digit code. Subsequent sign-ins require both the password and a current authenticator code. The MFA encryption key is generated in the repository root as `.admin_mfa_key` during enrollment and is excluded from Git. Keep a secure backup of this key: losing it makes enrolled MFA secrets unreadable. For deployment, set `STUDENT_BLOG_MFA_KEY` to base64-encoded 32-byte key material in the server's secret manager instead of relying on a local file.

The local `user` document root is a development convenience: it also exposes sibling folders such as `shared`. Do not use it as a production document root. For deployment, keep `user/public` as the public document root and configure explicit, protected server aliases or routes for the admin portals while denying direct web access to private configuration and shared files.

## Operational notes

- The database configuration is currently hard-coded in `user/shared/db_connect.php`; use environment-specific configuration and secrets management before deployment.
- Several feature pages create or update their own tables when loaded. The checked-in SQL is not a comprehensive migration system, so verify the database schema against the pages before deploying.
- Admin and super-admin sign-in both query the `admins` table. The repository does not provide a complete base schema or an initial super-admin bootstrap script; provision the first super-admin account through a secure, environment-specific process before use.
- Admin passwords are stored with PHP `password_hash()` (Argon2id when available, bcrypt otherwise) and checked with `password_verify()`. Successful sign-ins transparently upgrade legacy MD5 hashes. The first admin sign-in expands a short `admins.password` column to `VARCHAR(255)` and creates the `admin_login_attempts` table plus the super-admin MFA columns; the database account needs permission to perform these schema changes.
- Admin sessions use HTTP-only, SameSite cookies, store session files above the `user/` web roots, rotate their ID at login and role changes, expire after 30 minutes idle or 8 hours total, and re-check the account role and approved status in the database on protected requests. Repeated failures for the same account and client address trigger a 15-minute login lock.
- Super-admin accounts must complete TOTP authenticator enrollment before the first dashboard session. Keep `.admin_mfa_key` backed up securely or configure `STUDENT_BLOG_MFA_KEY` through the deployment environment; never commit or publish the key.
- No automated test or build commands are defined in `package.json`; it contains no project scripts or dependencies.

## Security and deployment

Use HTTPS, strong database credentials with least-privilege access, PHP production error settings, backups, and appropriate upload limits. Review authentication and authorization checks for every admin endpoint and uploaded-file handling before exposing the site publicly. Configure CSRF protections and rate limits consistently for state-changing actions. Keep credentials and user data out of source control and logs.

