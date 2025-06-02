#!/usr/bin/env bash
#
# install.sh — unattended setup for AdminLTE/PHP/MySQL admin panel.
#   1) Detect distro (apt vs yum/dnf) and install nginx, mariadb/mysql, php-fpm, php-mysql
#   2) Prompt for current MySQL root PW (if any) and new root PW
#   3) Prompt for PROJECT NAME → create DB and MySQL user with random password
#   4) Import sql/schema.sql + sql/dummy_data.sql into new DB
#   5) Ask for domain & SSL paths → write nginx vhost with per‐project logs
#   6) Create /var/www/<project>, copy files (except install.sh & sql/), set permissions
#   7) Write a fresh db.php into /var/www/<project>/ with correct credentials
#   8) Reload/restart services and print final URL & NEW database credentials
#
# USAGE: sudo ./install.sh
#

set -euo pipefail

#############################################################################
# 1) Must be run as root
#############################################################################
if [[ $EUID -ne 0 ]]; then
  echo "ERROR: This script must be run as root (or via sudo)."
  exit 1
fi

#############################################################################
# 2) Detect package manager and set install commands
#############################################################################
PKG_MANAGER=""
UPDATE_CMD=""
INSTALL_CMD=""
PHP_FPM_PKG=""
PHP_MYSQL_PKG=""

if command -v apt-get &>/dev/null; then
  PKG_MANAGER="apt"
  UPDATE_CMD="apt-get update -y"
  INSTALL_CMD="apt-get install -y"
  PHP_FPM_PKG="php-fpm"
  PHP_MYSQL_PKG="php-mysql"
elif command -v yum &>/dev/null; then
  PKG_MANAGER="yum"
  UPDATE_CMD="yum makecache -y"
  INSTALL_CMD="yum install -y"
  PHP_FPM_PKG="php-fpm"
  PHP_MYSQL_PKG="php-mysql"
elif command -v dnf &>/dev/null; then
  PKG_MANAGER="dnf"
  UPDATE_CMD="dnf makecache"
  INSTALL_CMD="dnf install -y"
  PHP_FPM_PKG="php-fpm"
  PHP_MYSQL_PKG="php-mysql"
else
  echo "ERROR: Could not detect apt-get or yum/dnf. Unsupported distro."
  exit 1
fi

echo "→ Detected package manager: $PKG_MANAGER"
echo "→ Updating package lists…"
$UPDATE_CMD

echo "→ Installing nginx, mysql-server (mariadb), php-fpm, and php-mysql…"
if [[ $PKG_MANAGER == "apt" ]]; then
  $INSTALL_CMD nginx mysql-server $PHP_FPM_PKG $PHP_MYSQL_PKG
elif [[ $PKG_MANAGER == "yum" || $PKG_MANAGER == "dnf" ]]; then
  $INSTALL_CMD epel-release -y || true
  $INSTALL_CMD nginx mariadb-server $PHP_FPM_PKG $PHP_MYSQL_PKG
  systemctl enable mariadb
  systemctl start mariadb
fi

# Ensure services are enabled & running
if [[ $PKG_MANAGER == "apt" ]]; then
  systemctl enable mysql
  systemctl start mysql
else
  systemctl enable mariadb
  systemctl start mariadb
fi
systemctl enable nginx
systemctl restart nginx

#############################################################################
# 3) Prompt for MySQL root password (current & new)
#############################################################################
echo
echo "------------------------------------------------------------"
echo " MySQL root account setup "
echo "------------------------------------------------------------"
read -rsp "1) Enter CURRENT MySQL root password (leave blank if none): " MYSQL_CURRENT_ROOT_PW
echo
while true; do
  read -rsp "2) Enter NEW MySQL root password: " MYSQL_NEW_ROOT_PW
  echo
  read -rsp "3) Confirm NEW MySQL root password: " _confirm
  echo
  if [[ "$MYSQL_NEW_ROOT_PW" == "$_confirm" && -n "$MYSQL_NEW_ROOT_PW" ]]; then
    break
  else
    echo ">> Passwords did not match or was empty—please try again."
  fi
done

# Apply the new root password.
if [[ -z "$MYSQL_CURRENT_ROOT_PW" ]]; then
  mysql --protocol=socket \
    -uroot <<_SQL
ALTER USER 'root'@'localhost' IDENTIFIED BY '$MYSQL_NEW_ROOT_PW';
FLUSH PRIVILEGES;
_SQL
else
  mysql --protocol=socket \
    -uroot -p"$MYSQL_CURRENT_ROOT_PW" <<_SQL
ALTER USER 'root'@'localhost' IDENTIFIED BY '$MYSQL_NEW_ROOT_PW';
FLUSH PRIVILEGES;
_SQL
fi

echo "✔ MySQL root password has been set."

#############################################################################
# 4) Prompt for PROJECT NAME → create DB, user, random password
#############################################################################
echo
echo "------------------------------------------------------------"
echo " Project / Database setup "
echo "------------------------------------------------------------"
while true; do
  read -p "Enter a PROJECT NAME (letters/numbers/underscores only): " PROJECT_NAME
  if [[ "$PROJECT_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    break
  else
    echo ">> Invalid name. Use only letters, numbers, or underscores."
  fi
done

DB_NAME="$PROJECT_NAME"
DB_USER="$PROJECT_NAME"
# Generate a 16-character random password (base64, remove '=+/' chars)
DB_PASS="$(openssl rand -base64 12 | tr -d '=+/')"

echo
echo "→ Creating MySQL database ‘$DB_NAME’ and user ‘$DB_USER’…"
mysql -uroot -p"$MYSQL_NEW_ROOT_PW" <<_SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
_SQL

echo "✔ Database '$DB_NAME' and user '$DB_USER' created."
echo "• DB password for user '$DB_USER': $DB_PASS"
echo

#############################################################################
# 4b) Import schema.sql + dummy_data.sql into the new database
#############################################################################
if [[ -f "./sql/schema.sql" ]]; then
  echo "→ Importing schema.sql into database '$DB_NAME'…"
  mysql -uroot -p"$MYSQL_NEW_ROOT_PW" "$DB_NAME" < ./sql/schema.sql
  echo "✔ schema.sql imported."
else
  echo "WARNING: ./sql/schema.sql not found—tables won’t be created."
fi

if [[ -f "./sql/dummy_data.sql" ]]; then
  echo "→ Importing dummy_data.sql into database '$DB_NAME'…"
  mysql -uroot -p"$MYSQL_NEW_ROOT_PW" "$DB_NAME" < ./sql/dummy_data.sql
  echo "✔ dummy_data.sql imported."
else
  echo "WARNING: ./sql/dummy_data.sql not found—no initial data loaded."
fi
echo

#############################################################################
# 5) Prompt for DOMAIN (DNS) → copy files & write db.php
#############################################################################
echo
echo "------------------------------------------------------------"
echo " Project directory & db.php configuration "
echo "------------------------------------------------------------"
WEBROOT_PARENT="/var/www"
DOC_ROOT="$WEBROOT_PARENT/$PROJECT_NAME"

# Create the project’s document root
mkdir -p "$DOC_ROOT"
echo "→ Created document root: $DOC_ROOT"

echo "→ Copying project files into $DOC_ROOT (excluding install.sh and sql/)..."
rsync -av --exclude="install.sh" --exclude="sql" ./ "$DOC_ROOT/" >/dev/null

# Determine webserver user/group
if [[ $PKG_MANAGER == "apt" ]]; then
  WWW_USER="www-data"
  WWW_GROUP="www-data"
else
  WWW_USER="nginx"
  WWW_GROUP="nginx"
fi
chown -R "$WWW_USER":"$WWW_GROUP" "$DOC_ROOT"
chmod -R 755 "$DOC_ROOT"
echo "✔ Files copied and permissions set to $WWW_USER:$WWW_GROUP."

# Overwrite or create a fresh db.php with the new credentials
DB_PHP_FILE="$DOC_ROOT/db.php"
cat > "$DB_PHP_FILE" <<EOF
<?php
// db.php — database connection using PDO
\$host = 'localhost';
\$db   = '$DB_NAME';
\$user = '$DB_USER';
\$pass = '$DB_PASS';
\$charset = 'utf8mb4';

\$dsn = "mysql:host=\$host;dbname=\$db;charset=\$charset";
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    \$pdo = new PDO(\$dsn, \$user, \$pass, \$options);
} catch (PDOException \$e) {
    die('Database connection failed: ' . \$e->getMessage());
}
?>
EOF

chown "$WWW_USER":"$WWW_GROUP" "$DB_PHP_FILE"
chmod 640 "$DB_PHP_FILE"
echo "✔ Wrote new db.php with project credentials."

echo
echo "→ Nginx server block and SSL setup coming next…"

#############################################################################
# 6) Prompt for DOMAIN name & SSL paths, then write Nginx vhost
#############################################################################
while true; do
  read -p "Enter the DOMAIN NAME for this project (e.g. example.com): " PROJECT_DOMAIN
  if [[ "$PROJECT_DOMAIN" =~ ^[A-Za-z0-9\.\-]+$ ]]; then
    break
  else
    echo ">> Invalid domain. Use letters, numbers, dots, or hyphens only."
  fi
done

echo
echo "→ Provide SSL certificate and key to enable HTTPS:"
read -p       "Path to SSL certificate file (e.g. /etc/ssl/certs/project.crt): " SSL_CERT_PATH
read -p       "Path to SSL key file       (e.g. /etc/ssl/private/project.key): " SSL_KEY_PATH

# Verify these files exist
if [[ ! -f "$SSL_CERT_PATH" ]]; then
  echo "ERROR: SSL certificate not found at $SSL_CERT_PATH"
  exit 1
fi
if [[ ! -f "$SSL_KEY_PATH" ]]; then
  echo "ERROR: SSL key not found at $SSL_KEY_PATH"
  exit 1
fi

# Find the PHP-FPM socket (Debian/Ubuntu) or fallback to /run/php-fpm/www.sock (CentOS/RHEL)
PHP_FPM_SOCK=""
if ls /run/php/php*-fpm.sock &>/dev/null; then
  PHP_FPM_SOCK="$(ls /run/php/php*-fpm.sock | head -n1)"
elif [[ -S "/run/php-fpm/www.sock" ]]; then
  PHP_FPM_SOCK="/run/php-fpm/www.sock"
else
  echo "ERROR: Could not locate a php-fpm socket (e.g. /run/php/phpX.Y-fpm.sock or /run/php-fpm/www.sock)."
  exit 1
fi

# Write the nginx configuration for this project
NGINX_CONF="/etc/nginx/sites-available/$PROJECT_NAME"

cat > "$NGINX_CONF" <<EOF
# ------------------------------------------------------------------------
# Nginx server block for $PROJECT_NAME
# ------------------------------------------------------------------------

server {
    listen 80;
    server_name $PROJECT_DOMAIN;
    # Redirect all HTTP → HTTPS
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name $PROJECT_DOMAIN;

    ssl_certificate     $SSL_CERT_PATH;
    ssl_certificate_key $SSL_KEY_PATH;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    root $DOC_ROOT;
    index index.php index.html index.htm;

    # Per‐project logs
    access_log /var/log/nginx/${PROJECT_NAME}-access.log;
    error_log  /var/log/nginx/${PROJECT_NAME}-error.log;

    # Main location
    location / {
        try_files \$uri \$uri/ =404;
    }

    # PHP-FPM processing
    location ~ \\.php\$ {
        include fastcgi_params;
        fastcgi_split_path_info ^(.+\\.php)(/.+)\$;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass unix:$PHP_FPM_SOCK;
    }

    # Deny access to .ht* files
    location ~ /\.ht {
        deny all;
    }
}
EOF

# Enable the new site and disable default
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/$PROJECT_NAME
if [[ -e /etc/nginx/sites-enabled/default ]]; then
  rm -f /etc/nginx/sites-enabled/default
fi

echo "✔ Nginx config created at $NGINX_CONF"
echo

#############################################################################
# 7) Final service reloads/restarts
#############################################################################
echo "→ Testing Nginx configuration…"
if nginx -t &>/dev/null; then
  echo "✔ Nginx config OK, reloading."
  systemctl reload nginx
else
  echo "ERROR: Nginx configuration test failed. Check $NGINX_CONF"
  exit 1
fi

# Restart PHP-FPM and MySQL/MariaDB
echo "→ Restarting PHP-FPM and MySQL…"
if systemctl is-enabled php*-fpm &>/dev/null; then
  systemctl restart php*-fpm
elif systemctl is-enabled php-fpm &>/dev/null; then
  systemctl restart php-fpm
fi

if [[ $PKG_MANAGER == "apt" ]]; then
  systemctl restart mysql
else
  systemctl restart mariadb
fi

echo "✔ All services restarted."

#############################################################################
# 8) Print final URL and NEW database credentials
#############################################################################
echo
echo "------------------------------------------------------------"
echo " INSTALLATION COMPLETE "
echo "------------------------------------------------------------"
echo "Project URL:  https://$PROJECT_DOMAIN"
echo
echo "New Database Credentials:"
echo "  • Database Name:   $DB_NAME"
echo "  • Database User:   $DB_USER"
echo "  • Database Pass:   $DB_PASS"
echo
echo "db.php has been written to: $DOC_ROOT/db.php"
echo "Document root: $DOC_ROOT"
echo "Nginx logs: /var/log/nginx/${PROJECT_NAME}-access.log  and  /var/log/nginx/${PROJECT_NAME}-error.log"
echo
echo "Make sure your DNS A/AAAA record for $PROJECT_DOMAIN points to this server’s IP."
echo "Visit https://$PROJECT_DOMAIN to see the login page."
echo

exit 0