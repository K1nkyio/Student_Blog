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
  admin/        Admin pages and report-management interface
  shared/       Database connection, shared page parts, helpers, CSRF and payment logic
  assets/       Front-end JavaScript and post images
  public/uploads/ Marketplace uploads
safespeak_setup.sql  Supplemental SafeSpeak tables and activity-log fields
```

## Requirements

- PHP with `mysqli` enabled (and `curl` if using the Daraja integration)
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

| Area | Route |
| --- | --- |
| Home | `/` (served from `user/public/index.php`) |
| Explore posts | `/explore.php` |
| Events | `/events.php` |
| Opportunities | `/opportunities.php` |
| Marketplace | `/marketplace.php` |
| Sign in / register | `/login.php` and `/register.php` |
| Admin sign in | `/admin/login.php` when the web server maps `user/admin` to `/admin`; configure this mapping explicitly |

The admin directory sits beside `public`, not beneath it. Configure an explicit server alias/rewrite for admin access (or a deliberate document root arrangement); do not assume `/admin` is directly exposed when `user/public` is the document root.

## Operational notes

- The database configuration is currently hard-coded in `user/shared/db_connect.php`; use environment-specific configuration and secrets management before deployment.
- Several feature pages create or update their own tables when loaded. The checked-in SQL is not a comprehensive migration system, so verify the database schema against the pages before deploying.
- Admin sign-in queries the `admins` table. The repository does not provide a complete base schema or an admin bootstrap script; provision an admin account through a secure, environment-specific process before use.
- The admin login currently compares an MD5 digest. Treat it as legacy authentication and upgrade password storage and verification before using the admin area in production.
- No automated test or build commands are defined in `package.json`; it contains no project scripts or dependencies.

## Security and deployment

Use HTTPS, strong database credentials with least-privilege access, PHP production error settings, backups, and appropriate upload limits. Review authentication and authorization checks for every admin endpoint and uploaded-file handling before exposing the site publicly. Configure CSRF protections and rate limits consistently for state-changing actions. Keep credentials and user data out of source control and logs.

