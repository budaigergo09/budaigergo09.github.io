#!/bin/bash

# PDC Darts Simulator - Quick Nginx Config
# Just points your existing nginx to this folder
# Run with: sudo bash configure_nginx.sh

PORT=8080
SITE_NAME="pdc-darts"
WEB_ROOT=$(pwd)

echo "Configuring nginx to serve: $WEB_ROOT"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run with sudo: sudo bash configure_nginx.sh"
    exit 1
fi

# Get PHP-FPM socket path
PHP_FPM_SOCK=$(find /var/run/php/ -name "*.sock" 2>/dev/null | head -1)
if [ -z "$PHP_FPM_SOCK" ]; then
    PHP_FPM_SOCK="/var/run/php/php-fpm.sock"
fi

# Set permissions for api/data folder only
mkdir -p "$WEB_ROOT/api/data"
chown -R www-data:www-data "$WEB_ROOT/api/data"
chmod -R 775 "$WEB_ROOT/api/data"

# Make sure www-data can read the web files
chmod -R 755 "$WEB_ROOT"
chown www-data:www-data "$WEB_ROOT/api" "$WEB_ROOT/api/index.php" 2>/dev/null

# Create nginx site config
cat > /etc/nginx/sites-available/$SITE_NAME << EOF
server {
    listen $PORT;
    listen [::]:$PORT;
    
    server_name _;
    root $WEB_ROOT;
    index darts.html index.html index.php;
    client_max_body_size 50M;
    
    location / {
        try_files \$uri \$uri/ =404;
    }
    
    location /api {
        try_files \$uri \$uri/ /api/index.php?\$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_FPM_SOCK;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /api/data/ {
        deny all;
    }
}
EOF

# Enable site, disable default
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/$SITE_NAME /etc/nginx/sites-enabled/

# Test and reload
nginx -t && systemctl reload nginx

echo ""
echo "Done! Your site is now at: http://localhost:$PORT"
echo "Or http://YOUR_SERVER_IP:$PORT"
