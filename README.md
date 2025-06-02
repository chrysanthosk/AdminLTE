# AdminLTE PHP Admin Panel

A full-stack PHP/MySQL admin panel built with AdminLTE. This repository includes:

- User authentication (login, logout, forgot password)
- Role-based admin dashboard
- User CRUD (Create, Read, Update, Delete)
- User profile page (name, email, password, 2FA, theme)
- Email/SMTP settings page
- Audit log of user actions
- Two-Factor Authentication (Google Authenticator)
- `install.sh` to automate server setup (Nginx, PHP-FPM, MySQL) and project configuration

---

## Table of Contents

- [Features](#features)
- [Prerequisites](#prerequisites)
- [Repository Structure](#repository-structure)
- [Installation](#installation)
    - [1. Clone the Repository](#1-clone-the-repository)
    - [2. Make `install.sh` Executable](#2-make-installsh-executable)
    - [3. Run the Installer](#3-run-the-installer)
    - [4. Finalize DNS & SSL](#4-finalize-dns--ssl)
- [Database Schema & Dummy Data](#database-schema--dummy-data)
- [Usage](#usage)
    - [Admin Login](#admin-login)
    - [Dashboard](#dashboard)
    - [User Administration](#user-administration)
    - [Profile Page](#profile-page)
    - [Email Settings](#email-settings)
    - [Audit Log](#audit-log)
- [Customizations](#customizations)
- [Troubleshooting](#troubleshooting)
- [License](#license)

---

## Features

1. **Authentication**
    - Login / Logout
    - “Forgot Password” placeholder page (no email logic by default)
    - Two-Factor Authentication via Google Authenticator
2. **Admin Dashboard**
    - Overview cards linking to Users, Email Settings, Audit Log
3. **User CRUD**
    - Add, edit, delete users (admin only)
    - Assign roles (`admin` or `user`)
    - Manage user name, email, theme, 2FA
4. **User Profile**
    - Change name, email, password
    - Enable/disable 2FA (QR code generation)
    - Toggle between Light / Dark theme
5. **Email Settings**
    - Store SMTP host, port, username, password, encryption method
    - Saved in `email_settings` table
6. **Audit Log**
    - Logs user actions (login, logout, add/edit/delete user, profile/email updates)
    - Viewable under “Admin → Audit Log”
7. **Automated Installer (`install.sh`)**
    - Detects Ubuntu/Debian or CentOS/RHEL
    - Installs Nginx, MySQL/MariaDB, PHP-FPM, PHP-MySQL extension
    - Prompts to set MySQL root password
    - Prompts for project name, auto-creates DB and MySQL user with random password
    - Updates `db.php` with new credentials
    - Prompts for domain name and SSL certificate paths, writes Nginx vhost with per-project logs
    - Copies project files into `/var/www/<project_name>` (owned by webserver user)

---

## Prerequisites

- A fresh or minimal Linux server (Ubuntu 20.04+, Debian 10+, CentOS 7+/RHEL 7+)
- **Root** or **sudo** privileges
- Valid SSL certificate and key files (PEM format) for your domain
- DNS A/AAAA record pointing your chosen domain (e.g. `example.com`) to the server’s IP

---

## Repository Structure

```
└── adminlte_project/
    ├── install.sh                ← Automated installer script
    ├── db.php                    ← Database connection (PDO)
    ├── auth.php                  ← Session & authentication helpers
    ├── GoogleAuthenticator.php   ← Minimal PHP TOTP class
    ├── index.php                 ← Redirects to login or dashboard
    ├── login.php                 
    ├── logout.php
    ├── forgot_password.php
    │
    ├── includes/
    │   ├── header.php            ← <head> + Navbar + theme logic
    │   ├── sidebar.php           ← AdminLTE sidebar menu (role-aware)
    │   └── footer.php            ← JS includes + footer
    │
    ├── pages/
    │   ├── dashboard.php         ← Admin dashboard overview
    │   ├── users.php             ← List & manage users (admin only)
    │   ├── add_user.php          ← Create new user form
    │   ├── edit_user.php         ← Edit existing user form
    │   ├── profile.php           ← Logged-in user’s profile
    │   ├── email_settings.php    ← SMTP settings form (admin only)
    │   └── audit_log.php         ← View audit logs (admin only)
    │
    └── sql/
        ├── schema.sql            ← MySQL schema (creates DB, tables, constraints)
        └── dummy_data.sql        ← Inserts initial data (admin/admin123, sample user & email_settings)
```

> **Note**: When `install.sh` runs, it copies everything except `install.sh` and the `sql/` folder into `/var/www/<project_name>/`.

---

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/your-repo.git
cd your-repo
```

### 2. Make `install.sh` Executable

```bash
chmod +x install.sh
```

### 3. Run the Installer

```bash
sudo ./install.sh
```

The script will prompt you for:

1. **Current MySQL root password** (if any)
2. **New MySQL root password** (and confirmation)
3. **Project name** (alphanumeric + underscores only)
4. **Domain name** (e.g. `example.com`)
5. **Absolute paths to your SSL certificate and key**

Behind the scenes, it will:

- Install Nginx, MySQL/MariaDB, PHP-FPM, and PHP-MySQL
- Configure MySQL root password
- Create a new database and a new MySQL user (same as project name) with a random password
- Update `/var/www/<project_name>/db.php` with the new credentials
- Copy all project files into `/var/www/<project_name>/`
- Chown files to the webserver user (`www-data` on Debian/Ubuntu, `nginx` on CentOS/RHEL)
- Create an Nginx vhost at `/etc/nginx/sites-available/<project_name>` that:
    - Redirects HTTP → HTTPS
    - Serves PHP via the discovered PHP-FPM socket
    - Uses per-project logs:
        - `/var/log/nginx/<project_name>-access.log`
        - `/var/log/nginx/<project_name>-error.log`
- Enable the site, disable the default site, reload Nginx, restart PHP-FPM & MySQL

At the end, you’ll see a summary like:

```
INSTALLATION COMPLETE
Project URL:  https://example.com

MySQL credentials for this project:
  • Database:        myproject
  • MySQL username:  myproject
  • MySQL password:  AbC123XyZ...

db.php was updated under /var/www/myproject/db.php
Document root: /var/www/myproject
Nginx logs: /var/log/nginx/myproject-access.log  and  /var/log/nginx/myproject-error.log
```

### 4. Finalize DNS & SSL

1. Ensure your DNS record (A/AAAA) points `example.com` (or your chosen domain) to this server’s IP.
2. Verify that your SSL certificate (`.crt`) and key (`.key`) files exist and are valid.
3. Browse to `https://example.com` to see the login page.

---

## Database Schema & Dummy Data

If you prefer to set up the database manually instead of using `install.sh`, you can import:

1. **`sql/schema.sql`**
   ```sql
   CREATE DATABASE IF NOT EXISTS admin_panel
     CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   USE admin_panel;

   CREATE TABLE IF NOT EXISTS users (
     id INT AUTO_INCREMENT PRIMARY KEY,
     username VARCHAR(50) UNIQUE NOT NULL,
     email VARCHAR(100) UNIQUE NOT NULL,
     password_hash VARCHAR(255) NOT NULL,
     role ENUM('admin', 'user') DEFAULT 'user',
     first_name VARCHAR(50),
     last_name VARCHAR(50),
     theme ENUM('light', 'dark') DEFAULT 'light',
     twofa_enabled TINYINT(1) DEFAULT 0,
     twofa_secret VARCHAR(255),
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );

   CREATE TABLE IF NOT EXISTS email_settings (
     id INT AUTO_INCREMENT PRIMARY KEY,
     smtp_host VARCHAR(255),
     smtp_port VARCHAR(10),
     smtp_user VARCHAR(100),
     smtp_pass VARCHAR(100),
     smtp_secure VARCHAR(10)
   );

   CREATE TABLE IF NOT EXISTS audit_logs (
     id INT AUTO_INCREMENT PRIMARY KEY,
     user_id INT,
     action TEXT,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
   );
   ```

2. **`sql/dummy_data.sql`**
   ```sql
   USE admin_panel;

   INSERT INTO users (username, email, password_hash, role, first_name, last_name, theme, twofa_enabled, twofa_secret) VALUES
   ('admin', 'admin@example.com', '<hashed_admin123>', 'admin', 'Admin', 'User', 'light', 0, NULL),
   ('user1', 'user1@example.com', '<hashed_user1pass>', 'user', 'User', 'One', 'light', 0, NULL);

   INSERT INTO email_settings (smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure) VALUES
   ('smtp.example.com', '587', 'user@example.com', 'securepassword', 'tls');
   ```

> **Note**: Replace `<hashed_admin123>` and `<hashed_user1pass>` with real bcrypt hashes if you import manually. When running `install.sh`, the script already inserts:
> - Admin user: `admin@example.com` / `admin123` (hashed automatically)
> - A sample user “user1” / `user1pass` (hashed automatically)
> - Default email settings entry

---

## Usage

### Admin Login

- Open `https://<your-domain>/login.php`
- Default administrator account (as created by `install.sh`):
    - **Email**: `admin@example.com`
    - **Password**: `admin123`
- You can change this later via the **User Administration** screen.

### Dashboard

- After logging in as an admin, you’ll see the AdminLTE dashboard with “small boxes” linking to:
    - **Users**
    - **Email Settings**
    - **Audit Log**

### User Administration

- Go to **Admin → User Administration** (sidebar menu).
- You can view all users in a table, add new users, edit existing users (change role, name, email, theme, password, 2FA), or delete users.
- Every action is logged to `audit_logs`.

### Profile Page

- Click your name in the sidebar (below the AdminLTE logo) to open **My Profile**.
- Update your first/last name, email, and theme (Light/Dark).
- Change password by entering a new password (or leave blank to keep current).
- Enable Two-Factor Authentication:
    1. Check “Enable 2FA”.
    2. If you have no existing secret, a new secret is generated and a QR code is displayed.
    3. Scan the QR code with Google Authenticator (or Authy).
    4. On your next login, you’ll be prompted for a 2FA code.

### Email Settings

- Go to **Admin → Email Settings**.
- Enter your SMTP host, port, username, password, and “Secure” (tls/ssl).
- Save to store these values in the `email_settings` table.
- You can integrate email functionality (e.g., password resets) by loading these values in your mailer.

### Audit Log

- Go to **Admin → Audit Log**.
- View a reverse-chronological table of all logged actions (user logins, logouts, profile or user updates, email settings changes, deletions, etc.).
- Each entry shows: ID, User (username), Action description, Timestamp.

---

## Customizations

1. **Change the Default Admin Credentials**
    - Edit the `dummy_data.sql` before importing, or create/modify the admin account via **User Administration**.
2. **Modify Database Connection**
    - The installer sets credentials in `/var/www/<project>/db.php`.
    - If you rename/move your database later, update `$host`, `$db`, `$user`, `$pass` accordingly.
3. **Adjust Nginx Settings**
    - The auto-generated vhost lives at `/etc/nginx/sites-available/<project_name>`.
    - To tweak PHP-FPM socket or add custom rewrites, edit that file and run `sudo nginx -t && sudo systemctl reload nginx`.
4. **Add More Fields to `users` Table**
    - Update `sql/schema.sql` and re-run migrations (or alter table manually).
    - Modify the PHP forms (`add_user.php`, `edit_user.php`, `profile.php`) to include new fields.
5. **Enable Real “Forgot Password” Emails**
    - Integrate a mailer library (e.g. PHPMailer) using credentials stored in `email_settings` table.
    - Implement token‐based reset links and a reset form.

---

## Troubleshooting

- **Nginx Fails to Start/Reload**
    1. Check syntax: `sudo nginx -t`.
    2. Inspect logs:
        - `/var/log/nginx/error.log` (global)
        - `/var/log/nginx/<project_name>-error.log` (project-specific)
    3. Confirm SSL files exist and have correct permissions.
- **PHP Pages Show “502 Bad Gateway”**
    - Ensure PHP-FPM is running:
      ```bash
      sudo systemctl status php*-fpm       # apt (e.g. php7.4-fpm)
      sudo systemctl status php-fpm         # yum/dnf
      ```  
    - Confirm the socket path in Nginx vhost matches `/run/php/...` or `/run/php-fpm/www.sock`.
- **Cannot Connect to MySQL**
    - Verify `db.php` credentials match what you set during install.
    - Test manually:
      ```bash
      mysql -u<db_user> -p'<db_pass>' -h localhost < <(echo "SHOW DATABASES;")
      ```  
- **“Access Denied” Errors**
    - Make sure the MySQL user has privileges on the database:
      ```sql
      SHOW GRANTS FOR '<db_user>'@'localhost';
      ```  
    - If needed, re-run:
      ```sql
      GRANT ALL PRIVILEGES ON `<db_name>`.* TO '<db_user>'@'localhost';
      FLUSH PRIVILEGES;
      ```

---

## License

This project uses the [MIT License](LICENSE). Feel free to adapt and redistribute as you see fit.
