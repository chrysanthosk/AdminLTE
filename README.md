# AdminLTE PHP/MySQL Dashboard
A fully-featured administrative dashboard built on AdminLTE, PHP, and MySQL with robust Role-Based Access Control (RBAC), modular dashboard widgets, and SMTP testing functionality.

## Table of Contents
1. [Overview](#overview)
2. [Features](#features)
3. [Requirements](#requirements)
4. [Installation](#installation)
    - [1. Clone or Upload Project](#1-clone-or-upload-project)
    - [2. Run install.sh](#2-run-installsh)
    - [3. Verify Installation](#3-verify-installation)
5. [Configuration](#configuration)
    - [Database (`db.php`)](#database-dbphp)
    - [Email Settings](#email-settings)
6. [Database Schema](#database-schema)
    - [Users & Authentication](#users--authentication)
    - [RBAC Tables](#rbac-tables)
    - [Modules Table](#modules-table)
    - [Email Settings Table](#email-settings-table)
    - [Audit Log Table](#audit-log-table)
7. [Project Structure](#project-structure)
8. [Usage](#usage)
    - [Login & Dashboard](#login--dashboard)
    - [Roles & Permissions](#roles--permissions)
    - [Modules Management](#modules-management)
    - [Email Settings & SMTP Test](#email-settings--smtp-test)
9. [Customizing](#customizing)
10. [Support & Logs](#support--logs)
11. [License](#license)

## Overview
This project provides a pre-built administrative interface using AdminLTE for the frontend and PHP/MySQL for the backend. It supports:
- **User Authentication** (login, logout, forgot password)
- **Role-Based Access Control (RBAC)** (roles, permissions, role_permission assignments)
- **Dynamic Dashboard Widgets** via a `modules` table
- **Email Settings** with SMTP test functionality using PHPMailer
- **Audit Logging** for critical actions.
- **Two-Factor Authentication (2FA)** via Google Authenticator

All features are accessed through a single `dashboard.php`, with displayed widgets depending on the logged-in user’s permissions.

## Features
- **Single Dashboard**: All users go to `dashboard.php`; widgets render dynamically based on permissions.
- **RBAC**:
    - **roles** table stores roles (e.g., `admin`, `user`, `manager`).
    - **permissions** table stores permission keys (e.g., `user.manage`, `role.manage`).
    - **role_permissions** links roles ↔ permissions.
- **Modules**:
    - A `modules` table defines dashboard widgets (title, description, icon, color, link, permission_key, sort_order, is_active).
    - **Modules Management** page to CRUD modules and toggle active/inactive.
- **Email Settings**:
    - A `email_settings` table holds SMTP host, port, username, password, secure (`tls`/`ssl`), `from_email`, and `from_name`.
    - **Email Settings** page to configure SMTP and test via PHPMailer.
- **Audit Log**: Tracks user actions (e.g., creating roles, updating settings).
- **2FA**: Google Authenticator integration for optional two-factor authentication.
- **Install Script**: `install.sh` automates server setup, DB creation, `db.php` generation, Nginx config, SSL, Composer, and PHPMailer installation.

## Requirements
- Debian/Ubuntu-based Linux
- Nginx (default site disabled)
- MySQL server
- PHP 8.3 with extensions: `fpm`, `mysql`, `mbstring`, `xml`, `zip`, `curl`
- Composer
- OpenSSL (for random DB password generation)
- Access to SSL certificates (PEM format)

## Installation

### 1. Clone or Upload Project
- Place your project source files (including `install.sh`, `pages/`, `includes/`, etc.) on the server.
- Ensure `install.sh` has execute permissions:
  ```bash
  chmod +x install.sh
  ```

### 2. Run install.sh
Execute `install.sh` with root privileges:
```bash
sudo ./install.sh
```
It will:
1. Detect Debian/Ubuntu and install Nginx, MySQL, PHP, and required extensions.
2. Prompt you to set the MySQL root password.
3. Ask for a project name (e.g., `adminlte`), create a database `${PROJECT_NAME}_db` and user `${PROJECT_NAME}_user` with a random password.
4. Generate `db.php` in `/var/www/${PROJECT_NAME}/db.php`.
5. Copy all project files (excluding `install.sh` and `sql/`) to `/var/www/${PROJECT_NAME}`, set proper permissions.
6. Prompt for a DNS/domain (e.g., `adminlte.example.com`) and SSL cert/key locations, then configure Nginx with HTTP→HTTPS redirect and HTTPS site.
7. Reload Nginx and PHP-FPM.
8. Install Composer (if missing) and `composer require phpmailer/phpmailer` in the project root.
9. Print out DB credentials and project URL.

### 3. Verify Installation
- Visit `https://${PROJECT_DOMAIN}` in your browser.
- You should see the login page (`login.php`) styled by AdminLTE.

## Configuration

### Database (`db.php`)
Located at `/var/www/${PROJECT_NAME}/db.php`:
```php
<?php
\$db_host   = 'localhost';
\$db_name   = 'project_db';
\$db_user   = 'project_user';
\$db_pass   = 'random_password';
\$db_charset = 'utf8mb4';

\$dsn = "mysql:host=\$db_host;dbname=\$db_name;charset=\$db_charset";
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    \$pdo = new PDO(\$dsn, \$db_user, \$db_pass, \$options);
} catch (\PDOException \$e) {
    throw new \PDOException(\$e->getMessage(), (int)\$e->getCode());
}
?>
```
Modify this only if you move the database or need different credentials.

### Email Settings
Navigate to **Admin → Email Settings** (`pages/email_settings.php`):
- **SMTP Host**: e.g., `smtp.example.com`
- **SMTP Port**: e.g., `587`
- **SMTP Username**: e.g., `user@example.com`
- **SMTP Password**: the user’s SMTP password
- **SMTP Secure**: `tls` or `ssl`
- **From Email Address**: the “From” address for outgoing mail (required)
- **Send Name**: the display name for outgoing mail (required)

To test:
1. Enter a valid **“Test Email Address”**.
2. Click **“Test SMTP”**. PHPMailer will attempt to send the test email.

Saved settings persist in the `email_settings` table.

## Database Schema

### Users & Authentication
- **users** (`id`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `twofa_enabled`, `twofa_secret`, `theme`, `created_at`)
- **roles** (`id`, `role_name`, `created_at`)
- **role_permissions** (`role_id`, `permission_id`)
- **permissions** (`id`, `permission_key`, `description`, `created_at`)
- **audit_log** (`id`, `user_id`, `action`, `timestamp`)

### Modules Table
```sql
CREATE TABLE modules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  icon_class VARCHAR(100) NOT NULL,
  box_color VARCHAR(50) NOT NULL,
  link VARCHAR(255) NOT NULL,
  permission_key VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```
- Defines dashboard widgets dynamically.
- Managed via **Admin → Modules** (`pages/modules.php`).

### Email Settings Table
```sql
CREATE TABLE email_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  smtp_host VARCHAR(255) NOT NULL,
  smtp_port INT NOT NULL,
  smtp_user VARCHAR(255) NOT NULL,
  smtp_pass VARCHAR(255) NOT NULL,
  smtp_secure VARCHAR(10) NOT NULL,
  from_email VARCHAR(255) NOT NULL,
  from_name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```
- Stores SMTP configuration and default From address/name.
- Used by `email_settings.php` to send test emails.

### Audit Log Table
```sql
CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(255) NOT NULL,
  timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```
- Logs critical user actions (e.g., permission changes, module edits).

## Project Structure
```
/project-root
├── includes
│   ├── auth.php         # Authentication helper (currentUser(), requirePermission())
│   ├── header.php       # Navbar and theme handling
│   ├── sidebar.php      # Sidebar with permission-based links
│   └── footer.php
├── pages
│   ├── login.php
│   ├── register.php
│   ├── forgot_password.php
│   ├── dashboard.php     # Unified, permission-driven dashboard
│   ├── users.php         # CRUD for users (permission: user.manage)
│   ├── roles.php         # CRUD for roles (permission: role.manage)
│   ├── role_permissions.php  # Assign permissions to roles (permission: role.assign)
│   ├── permissions.php      # CRUD for permissions (permission: permission.manage)
│   ├── modules.php          # CRUD for modules (permission: module.manage)
│   ├── email_settings.php   # Configure SMTP & test (permission: email.manage)
│   ├── audit_log.php        # View audit logs (permission: audit.view)
│   └── profile.php          # User profile (permission: profile.edit)
├── vendor                # Composer-installed dependencies (PHPMailer, etc.)
├── db.php                # Auto-generated by install.sh
├── install.sh            # Installation and setup script
├── README.md             # This file
└── .env (optional)       # For environment variables (if used)
```

## Usage

### Login & Dashboard
1. Go to `https://${PROJECT_DOMAIN}/login.php`.
2. **Admins** see all dashboard widgets (Users, Roles, Role Perms, Permissions, Modules, Email, Logs).
3. **Non-admins** see a welcome card and can edit their own profile under **Profile**.

### Roles & Permissions
- **Roles** page (`roles.php`): Create or rename roles.
- **Permissions** page (`permissions.php`): Create/edit/delete permission keys (e.g., `user.manage`, `module.manage`).
- **Role Permissions** page (`role_permissions.php`): Assign which permissions each role has.
- New roles automatically appear in **Role Management**.

### Modules Management
- Go to **Admin → Modules** (`pages/modules.php`).
- List of all modules (ID, Title, Description, Icon, Box Color, Link, Permission Key, Sort Order, Status).
- Click **Add Module** to create a new dashboard widget (choose icon, color, link, permission, order, active).
- Click **Edit** to modify an existing module’s properties, including Active/Inactive.
- Click **Delete** to remove a module.
- Only modules with `permission_key` that the current user has (and marked `is_active = TRUE`) appear on the dashboard.

### Email Settings & SMTP Test
- Navigate to **Admin → Email Settings** (`pages/email_settings.php`).
- Enter SMTP Host, Port, Username, Password, Secure (tls/ssl), **From Email**, and **Send Name**.
- **Save Settings** stores them in `email_settings`.
- Enter **Test Email Address** and click **Test SMTP** → PHPMailer attempts to send a test email using saved credentials, From Email, and Send Name.
- A success/failure message appears at the top.

## Customizing
- **Adding a new dashboard widget**:
    1. Create a new permission key in **Permissions** (e.g., `report.view`).
    2. Assign it to a role in **Role Permissions**.
    3. Go to **Modules**, click **Add Module**, and set:
        - Title: e.g. “Reports”
        - Description: e.g. “View Reports”
        - Icon Class: choose a Font Awesome option (e.g. `fas fa-chart-bar`)
        - Box Color: choose `bg-info`, `bg-success`, etc.
        - Link: e.g. `reports.php`
        - Permission Key: `report.view`
        - Sort Order: numeric order
        - Status: Active
    4. Save. The new widget appears on the dashboard for roles with `report.view`.

- **Locking down pages**: Add `requirePermission($pdo, '<permission.key>');` at the top of any new page to restrict access.

## Support & Logs
- **Nginx logs**:
    - Access: `/var/log/nginx/${PROJECT_NAME}-access.log`
    - Error: `/var/log/nginx/${PROJECT_NAME}-error.log`
- **PHP-FPM logs**: Default at `/var/log/php8.3-fpm.log` or similar.
- **Audit Log** page (`audit_log.php`) shows actions logged by `logAction($pdo, user_id, message)`.

## License
This project is released under the MIT License. See [LICENSE](LICENSE) for details.
