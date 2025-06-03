#!/usr/bin/env bash
#
# install.sh — Automated installer for AdminLTE PHP/MySQL project with SMTP testing support
#
# This script will:
#   1. Detect Debian/Ubuntu and install Nginx, PHP, MySQL, and required PHP extensions.
#   2. Prompt for MySQL root password to secure MySQL.
#   3. Ask for a project name, create a database and a dedicated MySQL user with a random password.
#   4. Write a `db.php` file in the project root with the new credentials.
#   5. Create a document root directory named after the project, copy all project files there
#      (except install.sh and SQL dumps).
#   6. Prompt for a DNS/domain to configure Nginx, plus paths to SSL certificate and key.
#   7. Generate an Nginx server block for the project with separate access/error logs.
#   8. Reload/restart Nginx and PHP‐FPM.
#   9. Install Composer (if not present) and run `composer require phpmailer/phpmailer` in the project root.
#  10. Display the database name, DB user, DB password, and project URL at the end.
#
# Usage:
#   sudo ./install.sh
#
# Notes:
#   - Run as root or under sudo.
#   - Requires Internet access to fetch packages and Composer installer.
#   - Assumes Debian/Ubuntu-based system.
#   - Customize PHP version (default: 8.3) if needed by modifying PHP_PKG variable.
#

set -euo pipefail

#=== 1. Detect Distro and Install Base Packages ===#

echo "---- Detecting Linux distribution ----"
# Load /etc/os-release if exists
if [ -r /etc/os-release ]; then
  . /etc/os-release
  DISTRO="$ID"
  VERSION_ID="$VERSION_ID"
else
  echo "Cannot detect distribution. Exiting."
  exit 1
fi

if [[ "$DISTRO" != "debian" && "$DISTRO" != "ubuntu" ]]; then
  echo "Unsupported distribution: $DISTRO. Exiting."
  exit 1
fi

echo "Distribution: $PRETTY_NAME"

# Update package lists
echo "---- Updating apt repositories ----"
apt-get update -y

# Install basic packages
echo "---- Installing Nginx, MySQL, PHP, and extensions ----"
PHP_VERSION="8.3"
apt-get install -y nginx mysql-server \
  php${PHP_VERSION}-fpm php${PHP_VERSION}-mysql php${PHP_VERSION}-cli php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml php${PHP_VERSION}-zip php${PHP_VERSION}-curl unzip wget

#=== 2. Secure MySQL Root Account ===#

echo
echo "---- Securing MySQL root user ----"
echo "Please enter a new MySQL root password. You'll be prompted twice."
read -s -p "New MySQL root password: " MYSQL_ROOT_PW
echo
read -s -p "Confirm MySQL root password: " MYSQL_ROOT_PW_CONFIRM
echo

if [[ "$MYSQL_ROOT_PW" != "$MYSQL_ROOT_PW_CONFIRM" ]]; then
  echo "Passwords do not match. Exiting."
  exit 1
fi

# Apply root password and remove insecure defaults
echo "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PW}'; FLUSH PRIVILEGES;" | mysql -u root

echo "MySQL root password set."

#=== 3. Prompt for Project Name and Create Database/User ===#

echo
echo "---- Project setup ----"
read -p "Enter a short lowercase project name (e.g. 'adminlte'): " PROJECT_NAME
PROJECT_NAME="${PROJECT_NAME,,}"  # lowercase
DOC_ROOT="/var/www/${PROJECT_NAME}"

# Generate random DB user password
DB_USER="${PROJECT_NAME}_user"
DB_PASS=$(openssl rand -base64 12)
DB_NAME="${PROJECT_NAME}_db"

echo
echo "Creating MySQL database and user..."
mysql -u root -p"${MYSQL_ROOT_PW}" <<EOF
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

echo "Database '${DB_NAME}' and user '${DB_USER}' created."

#=== 4. Write db.php ===#

echo
echo "---- Writing db.php ----"
DB_PHP_PATH="${DOC_ROOT}/db.php"
mkdir -p "$DOC_ROOT"
cat > "$DB_PHP_PATH" <<EOL
<?php
// db.php — database connection settings

\$db_host   = 'localhost';
\$db_name   = '${DB_NAME}';
\$db_user   = '${DB_USER}';
\$db_pass   = '${DB_PASS}';
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
EOL

chmod 640 "$DB_PHP_PATH"
echo "db.php written at $DB_PHP_PATH"

#=== 5. Copy Project Files to Document Root ===#

echo
echo "---- Copying project files to document root ----"
# Assume this script resides in project root where index.php, pages/, includes/, etc. exist
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
rsync -av --exclude='install.sh' --exclude='sql/' --exclude='vendor/' "$SCRIPT_DIR/" "$DOC_ROOT/"

# Fix permissions
chown -R www-data:www-data "$DOC_ROOT"
chmod -R 750 "$DOC_ROOT"

echo "Files copied to $DOC_ROOT"

#=== 6. Prompt for DNS/Domain and SSL cert paths ===#

echo
echo "---- Nginx configuration ----"
read -p "Enter the domain name for this project (e.g. adminlte.example.com): " PROJECT_DOMAIN

# Prompt for SSL certificate and key paths
read -p "Enter full path to SSL certificate (e.g. /etc/ssl/certs/your.crt): " SSL_CERT_PATH
read -p "Enter full path to SSL key (e.g. /etc/ssl/private/your.key): " SSL_KEY_PATH

#=== 7. Generate Nginx Server Block ===#

echo
echo "---- Creating Nginx server block ----"
NGINX_CONF="/etc/nginx/sites-available/${PROJECT_NAME}.conf"
cat > "$NGINX_CONF" <<EOL
server {
    listen 80;
    listen [::]:80;
    server_name ${PROJECT_DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${PROJECT_DOMAIN};

    root ${DOC_ROOT};
    index index.php index.html;

    ssl_certificate     ${SSL_CERT_PATH};
    ssl_certificate_key ${SSL_KEY_PATH};

    access_log /var/log/nginx/${PROJECT_NAME}-access.log;
    error_log  /var/log/nginx/${PROJECT_NAME}-error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOL

ln -sf "$NGINX_CONF" "/etc/nginx/sites-enabled/${PROJECT_NAME}.conf"

echo "Nginx config created at $NGINX_CONF"
echo "Enabling site and reloading Nginx..."

#=== 8. Restart Services ===#

systemctl restart php${PHP_VERSION}-fpm
systemctl reload nginx

echo "Nginx and PHP-FPM reloaded."

#=== 9. Install Composer & PHPMailer ===#

echo
echo "---- Installing Composer (if not present) ----"
if ! command -v composer >/dev/null 2>&1; then
  EXPECTED_SIG=$(wget -q -O - https://composer.github.io/installer.sig)
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  php -r "if (hash_file('SHA384', 'composer-setup.php') === '$EXPECTED_SIG') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); exit(1); } echo PHP_EOL;"
  php composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm composer-setup.php
  echo "Composer installed globally."
else
  echo "Composer is already installed."
fi

echo
echo "---- Installing PHPMailer in project via Composer ----"
cd "$DOC_ROOT"
if [ -f "composer.json" ]; then
  composer install --no-interaction --prefer-dist
else
  composer require phpmailer/phpmailer --no-interaction
fi
echo "PHPMailer installed."

#=== 10. Final Output ===#

echo
echo "========================================"
echo " Installation complete! Summary below: "
echo "========================================"
echo "Project Document Root: $DOC_ROOT"
echo "Project Domain:        https://${PROJECT_DOMAIN}"
echo
echo "Database Name:         $DB_NAME"
echo "Database User:         $DB_USER"
echo "Database Password:     $DB_PASS"
echo
echo "db.php generated at:    $DB_PHP_PATH"
echo "Nginx Config:          $NGINX_CONF"
echo "Access Log:            /var/log/nginx/${PROJECT_NAME}-access.log"
echo "Error Log:             /var/log/nginx/${PROJECT_NAME}-error.log"
echo
echo "Make sure DNS for ${PROJECT_DOMAIN} points to this server’s IP."
echo "Visit https://${PROJECT_DOMAIN} in your browser to see the site."
echo

exit 0