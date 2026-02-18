#!/bin/bash

# PDC Darts Simulator - Nginx Config for /admin/home/budaigergo09.github.io
# Run with: sudo bash setup_site.sh

WEB_ROOT=$(pwd)
PORT=8080
SITE_NAME="pdc-darts"

echo "Setting up nginx for: $WEB_ROOT"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Run with: sudo bash setup_site.sh"
    exit 1
fi

# Check if folder exists
if [ ! -d "$WEB_ROOT" ]; then
    echo "ERROR: Folder $WEB_ROOT does not exist!"
    exit 1
fi

# Find PHP-FPM socket
PHP_FPM_SOCK=$(find /var/run/php/ -name "*.sock" 2>/dev/null | head -1)
if [ -z "$PHP_FPM_SOCK" ]; then
    PHP_FPM_SOCK="/run/php/php-fpm.sock"
fi
echo "Using PHP socket: $PHP_FPM_SOCK"

# Set permissions - THIS IS THE KEY FIX
echo "Setting permissions..."

# Allow nginx to traverse to the folder
chmod 755 /home/admin
chmod 755 "$WEB_ROOT"

# API data folder for database
mkdir -p "$WEB_ROOT/api/data"
chown -R www-data:www-data "$WEB_ROOT/api/data"
chmod 775 "$WEB_ROOT/api/data"

# Create nginx config
echo "Creating nginx config..."
cat > /etc/nginx/sites-available/$SITE_NAME << EOF
server {
    listen $PORT;
    listen [::]:$PORT;
    
    server_name _;
    root $WEB_ROOT;
    index darts.html index.html index.php;
    
    # Logging for debugging
    access_log /var/log/nginx/pdc-darts-access.log;
    error_log /var/log/nginx/pdc-darts-error.log;
    
    location / {
        try_files \$uri \$uri/ =404;
    }
    
    location /api {
        try_files \$uri \$uri/ /api/index.php?\$query_string;
    }
    
    location ~ \.php\$ {
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

# Disable default, enable our site
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/$SITE_NAME /etc/nginx/sites-enabled/$SITE_NAME

# Test config
echo "Testing nginx config..."
nginx -t

if [ $? -eq 0 ]; then
    echo "Restarting nginx..."
    systemctl restart nginx
    
    echo ""
    echo "=================================="
    echo "Setup complete!"
    echo "=================================="
    echo "Site: http://YOUR_IP:$PORT"
    echo ""
    echo "Check if it works:"
    echo "  curl http://localhost:$PORT"
    echo ""
    echo "View errors:"
    echo "  sudo tail -f /var/log/nginx/pdc-darts-error.log"
else
    echo "Nginx config test FAILED!"
fi
