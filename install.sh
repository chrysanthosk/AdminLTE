#!/usr/bin/env bash
#
# install.sh — Automated installer for AdminLTE PHP/MySQL project with SMTP testing support
#
# This script will:
#   1. Detect Linux distribution (Debian/Ubuntu or RHEL/CentOS/Fedora) and install web server, database, PHP, extensions (including GD).
#   2. Prompt for MySQL/MariaDB root password to secure the database.
#   3. Ask for a project name, create a database and a dedicated DB user with a random password.
#   4. Write a `db.php` file in the project root with the new credentials.
#   5. Create a document root directory named after the project, copy project files there
#      (excluding install.sh, sql/, and vendor/).
#   6. Prompt for DNS/domain to configure Nginx, plus paths to SSL certificate and key.
#   7. Generate an Nginx server block for the project with separate access/error logs.
#   8. Reload/restart Nginx, PHP-FPM, and MySQL/MariaDB as appropriate.
#   9. Install Composer (if not present) and PHPMailer & PhpSpreadsheet via Composer in the project root.
#  10. Display the database name, DB user, DB password, and project URL at the end.
#
# Usage:
#   sudo ./install.sh
#
# Requirements:
#   - Run as root or under sudo.
#   - Internet access to fetch packages and Composer installer.
#

set -euo pipefail

#=== Helper Functions ===#

function detect_distro() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        DISTRO_ID="$ID"
        DISTRO_LIKE="$ID_LIKE"
    else
        echo "Cannot detect Linux distribution. Exiting."
        exit 1
    fi
}

function install_packages_debian() {
    apt-get update -y
    apt-get install -y nginx mysql-server \
      php8.3-fpm php8.3-mysql php8.3-cli php8.3-mbstring php8.3-xml php8.3-zip php8.3-curl php8.3-gd \
      unzip wget
}

function install_packages_redhat() {
    # Determine package manager (dnf preferred over yum)
    if command -v dnf >/dev/null 2>&1; then
        PKG_MGR="dnf"
    else
        PKG_MGR="yum"
    fi

    # Install EPEL if on CentOS/RHEL
    if [ "$PKG_MGR" == "yum" ]; then
        yum install -y epel-release
    fi

    $PKG_MGR install -y nginx mariadb-server \
      php php-cli php-mysqlnd php-mbstring php-xml php-zip php-curl php-gd php-fpm \
      unzip wget
}

function start_enable_services_debian() {
    systemctl enable --now php8.3-fpm
    systemctl enable --now mysql
    systemctl enable --now nginx
}

function start_enable_services_redhat() {
    systemctl enable --now php-fpm
    systemctl enable --now mariadb
    systemctl enable --now nginx
}

#=== 1. Detect Distro ===#

echo "---- Detecting Linux distribution ----"
detect_distro

case "$DISTRO_ID" in
    debian|ubuntu)
        echo "Debian/Ubuntu detected ($PRETTY_NAME)"
        PLATFORM="debian"
        ;;
    rhel|centos|fedora)
        echo "RHEL/CentOS/Fedora detected ($PRETTY_NAME)"
        PLATFORM="redhat"
        ;;
    *)
        if [[ "$DISTRO_LIKE" == *"debian"* ]]; then
            echo "Debian-like detected ($PRETTY_NAME)"
            PLATFORM="debian"
        elif [[ "$DISTRO_LIKE" == *"rhel"* || "$DISTRO_LIKE" == *"fedora"* ]]; then
            echo "RedHat-like detected ($PRETTY_NAME)"
            PLATFORM="redhat"
        else
            echo "Unsupported distribution: $DISTRO_ID. Exiting."
            exit 1
        fi
        ;;
esac

#=== 2. Install Base Packages ===#

echo
echo "---- Installing web server, database, PHP, and extensions (including GD) ----"
if [ "$PLATFORM" == "debian" ]; then
    install_packages_debian
elif [ "$PLATFORM" == "redhat" ]; then
    install_packages_redhat
fi

#=== 3. Secure Database (MySQL/MariaDB) Root Password ===#

echo
echo "---- Securing database root user ----"
read -s -p "Enter a new database root password: " DB_ROOT_PW
echo
read -s -p "Confirm database root password: " DB_ROOT_PW_CONFIRM
echo
if [[ "$DB_ROOT_PW" != "$DB_ROOT_PW_CONFIRM" ]]; then
    echo "Passwords do not match. Exiting."
    exit 1
fi

if [ "$PLATFORM" == "debian" ]; then
    # For MySQL
    echo "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_ROOT_PW}'; FLUSH PRIVILEGES;" | mysql -u root
elif [ "$PLATFORM" == "redhat" ]; then
    # For MariaDB
    echo "UPDATE mysql.user SET Password = PASSWORD('${DB_ROOT_PW}') WHERE User = 'root' AND Host = 'localhost'; FLUSH PRIVILEGES;" | mysql -u root
fi

echo "Database root password set."

#=== 4. Prompt for Project Name, Create DB & User ===#

echo
echo "---- Project setup ----"
read -p "Enter a short lowercase project name (e.g. 'adminlte'): " PROJECT_NAME
PROJECT_NAME="${PROJECT_NAME,,}"
DOC_ROOT="/var/www/${PROJECT_NAME}"

# Generate random DB user password
DB_USER="${PROJECT_NAME}_user"
DB_PASS=$(openssl rand -base64 12)
DB_NAME="${PROJECT_NAME}_db"

echo
echo "Creating database and user..."
if [ "$PLATFORM" == "debian" ]; then
    mysql -u root -p"${DB_ROOT_PW}" <<EOF
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
elif [ "$PLATFORM" == "redhat" ]; then
    mysql -u root -p"${DB_ROOT_PW}" <<EOF
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
fi

echo "Database '${DB_NAME}' and user '${DB_USER}' created."

#=== 5. Write db.php ===#

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
} catch (\\PDOException \$e) {
    throw new \\PDOException(\$e->getMessage(), (int)\$e->getCode());
}
?>
EOL

chmod 640 "$DB_PHP_PATH"
echo "db.php written at $DB_PHP_PATH"

#=== 6. Copy Project Files to Document Root ===#

echo
echo "---- Copying project files to document root ----"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
rsync -av --exclude='install.sh' --exclude='sql/' --exclude='vendor/' "$SCRIPT_DIR/" "$DOC_ROOT/"

chown -R www-data:www-data "$DOC_ROOT"
chmod -R 750 "$DOC_ROOT"

echo "Files copied to $DOC_ROOT"

#=== 7. Prompt for Domain and SSL cert/key ===#

echo
echo "---- Nginx Configuration ----"
read -p "Enter the domain name for this project (e.g. adminlte.example.com): " PROJECT_DOMAIN

read -p "Enter full path to SSL certificate (e.g. /etc/ssl/certs/your.crt): " SSL_CERT_PATH
read -p "Enter full path to SSL key (e.g. /etc/ssl/private/your.key): " SSL_KEY_PATH

#=== 8. Generate Nginx Server Block ===#

echo
echo "---- Creating Nginx server block ----"
NGINX_CONF="/etc/nginx/conf.d/${PROJECT_NAME}.conf"
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

    location ~ \\.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass unix:/run/php/${PHP_FPM_SOCKET};
    }

    location ~ /\\.ht {
        deny all;
    }
}
EOL

echo "Nginx config created at $NGINX_CONF"
echo "Reloading Nginx..."

systemctl reload nginx

#=== 9. Start and Enable Services ===#

echo
echo "---- Enabling and starting services ----"
if [ "$PLATFORM" == "debian" ]; then
    PHP_FPM_SERVICE="php8.3-fpm"
    start_enable_services_debian
elif [ "$PLATFORM" == "redhat" ]; then
    PHP_FPM_SERVICE="php-fpm"
    start_enable_services_redhat
fi

echo "Nginx, PHP-FPM, and database service started."

#=== 10. Install Composer & PHPMailer & PhpSpreadsheet ===#

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
echo "---- Installing PHPMailer and PhpSpreadsheet via Composer ----"
cd "$DOC_ROOT"
if [ -f "composer.json" ]; then
    composer install --no-interaction --prefer-dist
else
    composer require phpmailer/phpmailer phpoffice/phpspreadsheet --no-interaction
fi
echo "Composer dependencies installed."

#=== 11. Final Summary ===#

echo
echo "========================================"
echo " Installation Complete!               "
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
echo "Visit https://${PROJECT_DOMAIN} to see the site."
echo

exit 0